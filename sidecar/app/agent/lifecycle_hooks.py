"""
L — Lifecycle Hooks

Pre/post hooks for every agent invocation and tool call.
Hooks are the observability layer — they fire at defined lifecycle
points without coupling to core agent logic.

Hook failures are isolated: a broken hook never kills the agent.
Hooks are async — they can perform I/O (metrics, alerts, DB writes).

Phase 8A additions:
  - prometheus_metrics_hook: increments agent_invocations_total /
    agent_duration_seconds / agent_tool_calls_total counters
  - low_confidence_alert_hook: when confidence < threshold, POSTs to a
    Laravel webhook so the owner gets an actionable alert (and the row
    can be appended to ai_action_requests with full audit chain)
"""
from __future__ import annotations

import logging
import os
from dataclasses import dataclass
from typing import Any, Awaitable, Callable

import httpx

logger = logging.getLogger(__name__)

HookFn = Callable[["HookEvent"], Awaitable[None]]

LOW_CONFIDENCE_THRESHOLD = float(os.environ.get("CLINIC_LOW_CONFIDENCE_THRESHOLD", "0.35"))


@dataclass
class HookEvent:
    event: str                   # start | tool_call | tool_result | complete | error
    session_id: str
    case_token_prefix: str       # first 8 chars only — never log the full token
    data: dict[str, Any]
    agent: str = "clinical"      # persona label for metric labels


class LifecycleHooks:
    """
    Lifecycle hook runner — the L pillar.
    Hooks execute in registration order; failures are isolated.
    """

    def __init__(self, agent: str = "clinical") -> None:
        self._hooks: list[HookFn] = []
        self.agent = agent

    def register(self, fn: HookFn) -> None:
        self._hooks.append(fn)

    async def _fire(self, event: HookEvent) -> None:
        # Stamp the agent label onto the event so hooks don't need to be told
        if not event.agent:
            event.agent = self.agent
        for hook in self._hooks:
            try:
                await hook(event)
            except Exception as exc:
                logger.error(
                    "Lifecycle hook %s failed (isolated): %s",
                    getattr(hook, "__name__", "<hook>"),
                    exc,
                )

    async def on_start(self, body: Any, session_id: str) -> None:
        await self._fire(HookEvent(
            event="start",
            session_id=session_id,
            case_token_prefix=str(getattr(body, "case_token", ""))[:8],
            data={
                "age_band": getattr(body, "age_band", None),
                "gender": getattr(body, "gender", None),
            },
            agent=self.agent,
        ))

    async def on_tool_call(self, tool_name: str, session_id: str) -> None:
        await self._fire(HookEvent(
            event="tool_call",
            session_id=session_id,
            case_token_prefix="",
            data={"tool": tool_name},
            agent=self.agent,
        ))

    async def on_tool_result(self, tool_name: str, success: bool, session_id: str) -> None:
        await self._fire(HookEvent(
            event="tool_result",
            session_id=session_id,
            case_token_prefix="",
            data={"tool": tool_name, "success": success},
            agent=self.agent,
        ))

    async def on_complete(self, result: dict, session_id: str, duration_ms: int) -> None:
        await self._fire(HookEvent(
            event="complete",
            session_id=session_id,
            case_token_prefix="",
            data={
                "confidence": result.get("confidence"),
                "requires_human_review": result.get("requires_human_review"),
                "duration_ms": duration_ms,
                "citations_count": len(result.get("retrieval_citations", [])),
                "tool_count": result.get("_tool_count", 0),
            },
            agent=self.agent,
        ))

    async def on_error(self, error: str, session_id: str) -> None:
        await self._fire(HookEvent(
            event="error",
            session_id=session_id,
            case_token_prefix="",
            data={"error": error[:200]},
            agent=self.agent,
        ))


# ── Built-in hooks (registered by AgentHarness) ────────────────────────────


async def default_logging_hook(event: HookEvent) -> None:
    """Default hook: structured log line for every lifecycle event."""
    logger.info(
        "agent.%s.%s session=%s data=%s",
        event.agent,
        event.event,
        event.session_id[:12],
        event.data,
    )


def _confidence_band(value: float | None) -> str:
    if value is None:
        return "unknown"
    if value < 0.35:
        return "low"
    if value < 0.7:
        return "medium"
    return "high"


async def prometheus_metrics_hook(event: HookEvent) -> None:
    """
    Emits Prometheus counters/histograms for every lifecycle event.
    Imports the metrics lazily so unit tests that don't import the
    /metrics route don't double-register collectors.
    """
    try:
        from app.routes.metrics import (
            AGENT_DURATION,
            AGENT_INVOCATIONS,
            AGENT_LOW_CONFIDENCE,
            AGENT_TOOL_CALLS,
        )
    except Exception as exc:  # pragma: no cover
        logger.debug("prometheus_metrics_hook: metrics unavailable (%s)", exc)
        return

    if event.event == "complete":
        confidence = event.data.get("confidence")
        AGENT_INVOCATIONS.labels(
            agent=event.agent, confidence_band=_confidence_band(confidence)
        ).inc()
        duration_ms = event.data.get("duration_ms") or 0
        AGENT_DURATION.labels(agent=event.agent).observe(duration_ms / 1000.0)
        if confidence is not None and confidence < LOW_CONFIDENCE_THRESHOLD:
            AGENT_LOW_CONFIDENCE.labels(agent=event.agent).inc()
    elif event.event == "tool_result":
        AGENT_TOOL_CALLS.labels(
            agent=event.agent,
            tool=event.data.get("tool", "unknown"),
            outcome="ok" if event.data.get("success") else "error",
        ).inc()


async def low_confidence_alert_hook(event: HookEvent) -> None:
    """
    On agent.complete with confidence < threshold, POST to the configured
    Laravel webhook so the owner gets an `ai_action_requests` row.

    Failure mode: log and continue. Lifecycle hook failures are isolated;
    a missing webhook URL silently disables this hook.
    """
    if event.event != "complete":
        return
    confidence = event.data.get("confidence")
    if confidence is None or confidence >= LOW_CONFIDENCE_THRESHOLD:
        return

    webhook_url = os.environ.get("CLINIC_ALERT_WEBHOOK_URL")
    if not webhook_url:
        logger.debug("low_confidence_alert_hook: no webhook configured — skipping")
        return

    payload = {
        "agent": event.agent,
        "session_id": event.session_id,
        "confidence": confidence,
        "duration_ms": event.data.get("duration_ms"),
        "citations_count": event.data.get("citations_count", 0),
        "reason": "low_confidence",
    }
    secret = os.environ.get("CLINIC_ALERT_WEBHOOK_SECRET", "")

    try:
        async with httpx.AsyncClient(timeout=5.0) as c:
            await c.post(
                webhook_url,
                json=payload,
                headers={"X-Clinic-Alert-Secret": secret} if secret else {},
            )
    except Exception as exc:
        logger.warning("low_confidence_alert_hook: webhook POST failed (%s)", exc)
