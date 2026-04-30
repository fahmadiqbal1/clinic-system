"""
E — Execution Loop

Iterative tool-call → model-inference loop with retry-on-low-confidence.
Prevents context ROT by:
  - Capping at MAX_ITERATIONS (default 2)
  - Only injecting verified tool results into context
  - Delegating model calls to a single async method (easy to swap providers)
  - Reusing the same tool results across iterations (no duplicate calls)

Phase 1 — Pre-fetch: invoke all applicable tools in parallel.
Phase 2 — Inference: call the model with tool-enriched context.
Phase 3 — Verify inline: if confidence < retry_threshold AND iterations
          remain, append a "reconsider with additional rigor" assistant
          turn and re-call the model. Cap at MAX_ITERATIONS to prevent
          runaway loops and context bloat.
"""
from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass, field
from typing import Any

import httpx

from app.agent.context_manager import AgentContext
from app.agent.lifecycle_hooks import LifecycleHooks
from app.agent.tool_registry import Tool

logger = logging.getLogger(__name__)

MAX_ITERATIONS = 2
RETRY_CONFIDENCE_THRESHOLD = 0.40
OLLAMA_TIMEOUT_S = 600.0  # 10-min ceiling; llama3.2:3b can take 3-5 min on first full prompt


@dataclass
class LoopResult:
    rationale: str
    iterations: int
    tool_results: list[dict] = field(default_factory=list)
    model_id: str = ""


class ExecutionLoop:
    """Orchestrates tool pre-fetch + model inference — the E pillar."""

    def __init__(
        self,
        verification: Any | None = None,
        retry_threshold: float = RETRY_CONFIDENCE_THRESHOLD,
        max_iterations: int = MAX_ITERATIONS,
    ) -> None:
        # Verification is optional so legacy unit tests that instantiate
        # ExecutionLoop() without args keep working. When present, it
        # enables the retry-on-low-confidence path.
        self.verification = verification
        self.retry_threshold = retry_threshold
        self.max_iterations = max(1, max_iterations)

    async def run(
        self,
        context: AgentContext,
        tools: list[Tool],
        lifecycle: LifecycleHooks,
        session_id: str,
        ollama_url: str,
        model: str,
        body: Any | None = None,
    ) -> LoopResult:
        tool_results: list[dict] = []

        # ── Phase 1: Pre-fetch tools in parallel ───────────────────────────
        if tools:
            tool_tasks = []
            for tool in tools:
                await lifecycle.on_tool_call(tool.name, session_id)
                tool_tasks.append(tool.invoke({"context": context}))

            results = await asyncio.gather(*tool_tasks, return_exceptions=True)

            for tool, result in zip(tools, results):
                if isinstance(result, Exception):
                    result = {"error": str(result), "tool": tool.name}
                tool_results.append(result)
                await lifecycle.on_tool_result(
                    tool.name, "error" not in result, session_id
                )

        # ── Inject tool results once — same context across iterations ──────
        messages = context.inject_tool_results(tool_results)

        # ── Phase 2: First inference ───────────────────────────────────────
        rationale = await self._call_ollama(messages, ollama_url, model)
        iterations = 1

        # ── Phase 3: Optional retry on low confidence ──────────────────────
        if (
            self.verification is not None
            and self.max_iterations > 1
            and rationale
        ):
            try:
                vr = self.verification.validate(rationale, body)
                if vr.confidence < self.retry_threshold:
                    logger.info(
                        "ExecutionLoop: confidence %.2f below retry threshold %.2f — re-running with reconsider prompt",
                        vr.confidence,
                        self.retry_threshold,
                    )
                    retry_messages = list(messages) + [
                        {"role": "assistant", "content": rationale},
                        {
                            "role": "user",
                            "content": (
                                "Your previous response indicated low certainty. "
                                "Reconsider the case using the retrieved guidelines and "
                                "structured patient data above. Re-answer using the SAME "
                                "section structure (## ASSESSMENT / ## DIFFERENTIALS / "
                                "## RECOMMENDATIONS / ## CONFIDENCE). If data is genuinely "
                                "insufficient, state that explicitly in ASSESSMENT and keep "
                                "CONFIDENCE: low."
                            ),
                        },
                    ]
                    retry_rationale = await self._call_ollama(
                        retry_messages, ollama_url, model
                    )
                    iterations = 2
                    if retry_rationale:
                        # Take the retry only if it doesn't lower confidence further
                        try:
                            vr2 = self.verification.validate(retry_rationale, body)
                            if vr2.confidence >= vr.confidence:
                                rationale = retry_rationale
                        except Exception:
                            rationale = retry_rationale
            except Exception as exc:
                logger.warning(
                    "ExecutionLoop: verification-driven retry skipped (%s)", exc
                )

        return LoopResult(
            rationale=rationale,
            iterations=iterations,
            tool_results=tool_results,
            model_id=f"{model}:etcslv:v1",
        )

    async def _call_ollama(
        self, messages: list[dict], ollama_url: str, model: str
    ) -> str:
        from app.agent.model_provider import call_model
        try:
            return await call_model(messages, ollama_url, model, timeout_s=OLLAMA_TIMEOUT_S)
        except (httpx.RequestError, httpx.TimeoutException) as exc:
            raise RuntimeError(f"Model provider unreachable: {type(exc).__name__}: {exc}") from exc
