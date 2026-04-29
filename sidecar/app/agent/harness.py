"""
ETCSLV Agent Harness — Agent = Model + Harness

The harness governs every AI consultation through six pillars:

  E — Execution Loop      : Iterative model + tool orchestration
  T — Tool Registry       : Declarative tool catalogue with schemas
  C — Context Manager     : Token-budgeted context assembly with anti-ROT
  S — State Store         : Per-case persistence across consultations
  L — Lifecycle Hooks     : Pre/post hooks for observability
  V — Verification        : Output validation and quality gates

Architecture:
  ConsultInput
      │
      ▼
  LifecycleHooks.on_start()          ← L
      │
      ▼
  StateStore.get(case_token)         ← S (prior context)
      │
      ▼
  ContextManager.build(body, prior)  ← C (token budget, anti-ROT)
      │
      ▼
  ToolRegistry.resolve(context)      ← T (which tools fire)
      │
      ▼
  ExecutionLoop.run(ctx, tools)      ← E (pre-fetch + inference + retry)
      │
      ▼
  VerificationInterface.validate()   ← V (quality gates + confidence)
      │
      ▼
  StateStore.put(case_token, result) ← S (persist for next call)
      │
      ▼
  LifecycleHooks.on_complete()       ← L
      │
      ▼
  ConsultOutput

The harness connects through MCP — each pillar is independently
swappable without changing the interface.

Phase 8A — pillar upgrades to 10/10:
  • C: injectable system prompt + summary compression for prior state
  • S: Redis backend (when REDIS_URL set) + namespace
  • L: prometheus_metrics_hook + low_confidence_alert_hook auto-registered
  • V: passed-bug fix + hallucination heuristic + section completeness score
  • T: vital_alert + medication_safety added alongside rag_query
  • E: retry-on-low-confidence (max 2 iterations)
"""
from __future__ import annotations

import hashlib
import logging
import os
import time
import uuid

from app.agent.clinical_tools import (
    make_medication_safety_tool,
    make_rag_query_tool,
    make_vital_alert_tool,
)
from app.agent.context_manager import CLINICAL_SYSTEM_PROMPT, ContextManager
from app.agent.execution_loop import ExecutionLoop
from app.agent.lifecycle_hooks import (
    LifecycleHooks,
    default_logging_hook,
    low_confidence_alert_hook,
    prometheus_metrics_hook,
)
from app.agent.state_store import StateStore
from app.agent.tool_registry import ToolRegistry
from app.agent.verification_interface import VerificationInterface
from app.schemas.medical import ConsultInput, ConsultOutput

logger = logging.getLogger(__name__)


class AgentHarness:
    """
    ETCSLV harness — instantiate once per persona at app startup.
    Call run() once per consultation request.

    Per-persona configuration:
      agent             — label used by metrics + logs ("clinical" by default)
      system_prompt     — injected into ContextManager
      verification      — VerificationInterface with persona-specific gates
      build_tools(body) — function that returns the tool list for a request
    """

    def __init__(
        self,
        agent: str = "clinical",
        system_prompt: str | None = None,
        verification: VerificationInterface | None = None,
        ollama_url: str | None = None,
        model: str | None = None,
    ) -> None:
        self.agent = agent
        self.context_manager = ContextManager(
            system_prompt=system_prompt or CLINICAL_SYSTEM_PROMPT
        )
        self.verification = verification or VerificationInterface()
        self.execution_loop = ExecutionLoop(verification=self.verification)
        self.state_store = StateStore(namespace=agent)
        self.lifecycle = LifecycleHooks(agent=agent)

        self.ollama_url = ollama_url or os.environ.get("OLLAMA_URL", "http://ollama:11434")
        self.model = model or os.environ.get("OLLAMA_MODEL", "medgemma")

        # Standard hooks for every persona
        self.lifecycle.register(default_logging_hook)
        self.lifecycle.register(prometheus_metrics_hook)
        self.lifecycle.register(low_confidence_alert_hook)

        logger.info(
            "AgentHarness[%s] initialised (ollama=%s model=%s redis=%s)",
            agent,
            self.ollama_url,
            self.model,
            self.state_store.is_redis,
        )

    def build_tools(self, body: ConsultInput) -> ToolRegistry:
        """
        Default clinical tool set. Subclasses (admin / ops / compliance)
        override this to wire their own tools.
        """
        registry = ToolRegistry()
        registry.register(make_rag_query_tool(body))
        registry.register(make_vital_alert_tool(body))
        registry.register(make_medication_safety_tool(body))
        return registry

    async def run(
        self, body: ConsultInput, session_id: str | None = None
    ) -> ConsultOutput:
        """Execute the full ETCSLV pipeline for a single consultation."""
        if session_id is None:
            session_id = str(uuid.uuid4())

        start_ms = int(time.time() * 1000)

        # ── L: lifecycle start ──────────────────────────────────────────
        await self.lifecycle.on_start(body, session_id)

        try:
            # ── S: load prior consultation state ───────────────────────
            prior_state = await self.state_store.get(body.case_token)

            # ── C: assemble token-budgeted context ─────────────────────
            context = self.context_manager.build(body, prior_state)
            context.session_id = session_id

            # ── T: build tool registry for this request ─────────────────
            registry = self.build_tools(body)
            tools = registry.resolve({"context": context})

            # ── E: execute loop (pre-fetch tools → model inference → retry) ─
            loop_result = await self.execution_loop.run(
                context=context,
                tools=tools,
                lifecycle=self.lifecycle,
                session_id=session_id,
                ollama_url=self.ollama_url,
                model=self.model,
                body=body,
            )

            # ── V: validate output ──────────────────────────────────────
            vr = self.verification.validate(loop_result.rationale, body)

            if not vr.passed:
                logger.warning(
                    "VerificationInterface[%s]: output issues %s for session %s",
                    self.agent,
                    vr.issues,
                    session_id[:12],
                )

            # Build real citations from RAGFlow tool results
            citations: list[str] = []
            for tr in loop_result.tool_results:
                citations.extend(tr.get("citations", []))

            result_dict = {
                "model_id": loop_result.model_id,
                "prompt_hash": hashlib.sha256(
                    context.messages[-1]["content"].encode()
                ).hexdigest(),
                "rationale": loop_result.rationale,
                "confidence": vr.confidence,
                "requires_human_review": vr.requires_review,
                "retrieval_citations": citations[:5],
                "verification_issues": vr.issues,
                "_tool_count": len(loop_result.tool_results),
                "_iterations": loop_result.iterations,
            }

            # ── S: persist state for next consultation ──────────────────
            await self.state_store.put(body.case_token, result_dict)

            # ── L: lifecycle complete ───────────────────────────────────
            duration_ms = int(time.time() * 1000) - start_ms
            await self.lifecycle.on_complete(result_dict, session_id, duration_ms)

            return ConsultOutput(
                model_id=result_dict["model_id"],
                prompt_hash=result_dict["prompt_hash"],
                rationale=result_dict["rationale"],
                confidence=result_dict["confidence"],
                requires_human_review=result_dict["requires_human_review"],
                retrieval_citations=result_dict["retrieval_citations"],
                verification_issues=result_dict["verification_issues"],
            )

        except Exception as exc:
            await self.lifecycle.on_error(str(exc), session_id)
            raise


# ── Module-level singleton — created once at app startup ────────────────
_harness: AgentHarness | None = None


def get_harness() -> AgentHarness:
    global _harness
    if _harness is None:
        _harness = AgentHarness(agent="clinical")
    return _harness
