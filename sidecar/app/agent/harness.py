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
  ExecutionLoop.run(ctx, tools)      ← E (pre-fetch + inference)
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
"""
from __future__ import annotations

import hashlib
import logging
import os
import time
import uuid

from app.agent.context_manager import ContextManager
from app.agent.execution_loop import ExecutionLoop
from app.agent.lifecycle_hooks import LifecycleHooks, default_logging_hook
from app.agent.state_store import StateStore
from app.agent.tool_registry import Tool, ToolRegistry
from app.agent.verification_interface import VerificationInterface
from app.schemas.medical import ConsultInput, ConsultOutput
from app.services import ragflow

logger = logging.getLogger(__name__)


def _make_rag_tool(body: ConsultInput) -> Tool:
    """
    Build a RAGFlow retrieval tool pre-bound to this case's chief complaint.
    Fail-open: if RAGFlow is down or unconfigured, returns empty citations.
    """
    query_text = (
        body.vitals.chief_complaint
        if body.vitals and body.vitals.chief_complaint
        else "clinical assessment guidelines"
    )

    async def _invoke(ctx: dict) -> dict:
        result = await ragflow.query(query_text, collection="general")
        return {
            "tool": "rag_query",
            "answer": result.get("answer", ""),
            "citations": result.get("citations", []),
        }

    return Tool(
        name="rag_query",
        description="Retrieve relevant clinical guidelines from the RAGFlow knowledge base.",
        schema={
            "type": "object",
            "properties": {"query": {"type": "string"}},
        },
        invoke_fn=_invoke,
        fail_open=True,
    )


class AgentHarness:
    """
    ETCSLV harness — instantiate once per app startup.
    Call run() once per consultation request.
    """

    def __init__(self) -> None:
        self.context_manager = ContextManager()         # C
        self.execution_loop = ExecutionLoop()            # E
        self.state_store = StateStore()                  # S
        self.lifecycle = LifecycleHooks()                # L
        self.verification = VerificationInterface()      # V

        self.ollama_url = os.environ.get("OLLAMA_URL", "http://ollama:11434")
        self.model = os.environ.get("OLLAMA_MODEL", "medgemma")

        # Default hook: structured logging
        self.lifecycle.register(default_logging_hook)

        logger.info(
            "AgentHarness initialised (ollama=%s model=%s)", self.ollama_url, self.model
        )

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
            registry = ToolRegistry()
            registry.register(_make_rag_tool(body))

            tools = registry.resolve({"context": context})

            # ── E: execute loop (pre-fetch tools → model inference) ─────
            loop_result = await self.execution_loop.run(
                context=context,
                tools=tools,
                lifecycle=self.lifecycle,
                session_id=session_id,
                ollama_url=self.ollama_url,
                model=self.model,
            )

            # ── V: validate output ──────────────────────────────────────
            vr = self.verification.validate(loop_result.rationale, body)

            if not vr.passed:
                logger.warning(
                    "VerificationInterface: output issues %s for session %s",
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
                "_tool_count": len(loop_result.tool_results),
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
            )

        except Exception as exc:
            await self.lifecycle.on_error(str(exc), session_id)
            raise


# ── Module-level singleton — created once at app startup ────────────────
_harness: AgentHarness | None = None


def get_harness() -> AgentHarness:
    global _harness
    if _harness is None:
        _harness = AgentHarness()
    return _harness
