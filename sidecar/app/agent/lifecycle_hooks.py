"""
L — Lifecycle Hooks

Pre/post hooks for every agent invocation and tool call.
Hooks are the observability layer — they fire at defined lifecycle
points without coupling to core agent logic.

Hook failures are isolated: a broken hook never kills the agent.
Hooks are async — they can perform I/O (metrics, alerts, DB writes).
"""
from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Any, Awaitable, Callable

logger = logging.getLogger(__name__)

HookFn = Callable[["HookEvent"], Awaitable[None]]


@dataclass
class HookEvent:
    event: str                   # start | tool_call | tool_result | complete | error
    session_id: str
    case_token_prefix: str       # first 8 chars only — never log the full token
    data: dict[str, Any]


class LifecycleHooks:
    """
    Lifecycle hook runner — the L pillar.
    Hooks execute in registration order; failures are isolated.
    """

    def __init__(self) -> None:
        self._hooks: list[HookFn] = []

    def register(self, fn: HookFn) -> None:
        self._hooks.append(fn)

    async def _fire(self, event: HookEvent) -> None:
        for hook in self._hooks:
            try:
                await hook(event)
            except Exception as exc:
                logger.error("Lifecycle hook %s failed (isolated): %s", hook.__name__, exc)

    async def on_start(self, body: Any, session_id: str) -> None:
        await self._fire(HookEvent(
            event="start",
            session_id=session_id,
            case_token_prefix=str(body.case_token)[:8],
            data={"age_band": body.age_band, "gender": body.gender},
        ))

    async def on_tool_call(self, tool_name: str, session_id: str) -> None:
        await self._fire(HookEvent(
            event="tool_call",
            session_id=session_id,
            case_token_prefix="",
            data={"tool": tool_name},
        ))

    async def on_tool_result(self, tool_name: str, success: bool, session_id: str) -> None:
        await self._fire(HookEvent(
            event="tool_result",
            session_id=session_id,
            case_token_prefix="",
            data={"tool": tool_name, "success": success},
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
        ))

    async def on_error(self, error: str, session_id: str) -> None:
        await self._fire(HookEvent(
            event="error",
            session_id=session_id,
            case_token_prefix="",
            data={"error": error[:200]},
        ))


async def default_logging_hook(event: HookEvent) -> None:
    """Default hook: structured log line for every lifecycle event."""
    logger.info(
        "agent.%s session=%s data=%s",
        event.event,
        event.session_id[:12],
        event.data,
    )
