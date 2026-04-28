"""
E — Execution Loop

Iterative tool-call → model-inference loop.
Prevents context ROT by:
  - Capping at MAX_ITERATIONS (default 2)
  - Only injecting verified tool results into context
  - Delegating model calls to a single async method (easy to swap providers)

Phase 1 — Pre-fetch: invoke all applicable tools in parallel
Phase 2 — Inference: call Ollama with tool-enriched context
"""
from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass, field

import httpx

from app.agent.context_manager import AgentContext
from app.agent.lifecycle_hooks import LifecycleHooks
from app.agent.tool_registry import Tool

logger = logging.getLogger(__name__)

MAX_ITERATIONS = 2
OLLAMA_TIMEOUT_S = 120.0


@dataclass
class LoopResult:
    rationale: str
    iterations: int
    tool_results: list[dict] = field(default_factory=list)
    model_id: str = ""


class ExecutionLoop:
    """Orchestrates tool pre-fetch + model inference — the E pillar."""

    async def run(
        self,
        context: AgentContext,
        tools: list[Tool],
        lifecycle: LifecycleHooks,
        session_id: str,
        ollama_url: str,
        model: str,
    ) -> LoopResult:
        tool_results: list[dict] = []

        # Phase 1: Pre-fetch tools in parallel
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

        # Inject successful tool results into context
        messages = context.inject_tool_results(tool_results)

        # Phase 2: Model inference
        rationale = await self._call_ollama(messages, ollama_url, model)

        return LoopResult(
            rationale=rationale,
            iterations=1,
            tool_results=tool_results,
            model_id=f"{model}:etcslv:v1",
        )

    async def _call_ollama(
        self, messages: list[dict], ollama_url: str, model: str
    ) -> str:
        try:
            async with httpx.AsyncClient(timeout=OLLAMA_TIMEOUT_S) as client:
                resp = await client.post(
                    f"{ollama_url}/v1/chat/completions",
                    json={
                        "model": model,
                        "messages": messages,
                        "max_tokens": 2048,
                        "temperature": 0.3,
                    },
                    headers={"bypass-tunnel-reminder": "true"},
                )
        except httpx.RequestError as exc:
            raise RuntimeError(f"Ollama unreachable: {exc}") from exc

        if resp.status_code != 200:
            raise RuntimeError(
                f"Ollama returned {resp.status_code}: {resp.text[:200]}"
            )

        data = resp.json()
        return (
            data.get("choices", [{}])[0].get("message", {}).get("content")
            or "No response generated."
        )
