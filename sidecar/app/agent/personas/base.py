"""
PersonaHarness — shared ETCSLV pipeline for non-clinical agents.

Differs from the clinical AgentHarness in two ways:
  1. Input schema is generic (admin / ops / compliance schemas). The harness
     doesn't read .vitals / .medications etc. — it expects subclasses to
     supply a build_user_content(body) that produces the model-facing text.
  2. case_token-equivalent is body.session_token. State is namespaced per
     persona, so all three personas can share Redis without collisions.

The 6 ETCSLV pillars run in the same order and with the same isolation as
the clinical harness:
  L on_start → S get → C build → T resolve → E run → V validate → S put → L on_complete
"""
from __future__ import annotations

import hashlib
import logging
import os
import time
import uuid
from typing import Any, Callable

from app.agent.context_manager import AgentContext, ContextManager
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

logger = logging.getLogger(__name__)


class PersonaHarness:
    """
    Shared ETCSLV pipeline for the admin / ops / compliance personas.
    Subclasses provide:
      - SYSTEM_PROMPT
      - build_user_content(body) → str  (what the model sees as the user turn)
      - build_tools(body) → ToolRegistry
      - extract_session_token(body) → str  (default: body.session_token)
      - Optional: post_process(rationale, body) → dict  (extra fields for output)
    """

    SYSTEM_PROMPT: str = "You are an AI assistant. Output structured findings."

    def __init__(
        self,
        agent: str,
        verification: VerificationInterface,
        ollama_url: str | None = None,
        model: str | None = None,
        token_budget: int = 6000,
    ) -> None:
        self.agent = agent
        self.context_manager = ContextManager(
            system_prompt=self.SYSTEM_PROMPT, token_budget=token_budget
        )
        self.verification = verification
        self.execution_loop = ExecutionLoop(verification=self.verification)
        self.state_store = StateStore(namespace=agent)
        self.lifecycle = LifecycleHooks(agent=agent)

        # Persona personas use a stronger reasoning model than MedGemma.
        # Owners can override per-persona via PERSONA_<NAME>_MODEL env var.
        env_model_var = f"OLLAMA_MODEL_{agent.upper()}"
        self.ollama_url = ollama_url or os.environ.get("OLLAMA_URL", "http://ollama:11434")
        self.model = (
            model
            or os.environ.get(env_model_var)
            or os.environ.get("OLLAMA_MODEL_PERSONA", "llama3.1:8b")
        )

        self.lifecycle.register(default_logging_hook)
        self.lifecycle.register(prometheus_metrics_hook)
        self.lifecycle.register(low_confidence_alert_hook)

        logger.info(
            "PersonaHarness[%s] initialised (model=%s redis=%s)",
            agent, self.model, self.state_store.is_redis,
        )

    # ── Subclass hooks ──────────────────────────────────────────────────
    def build_user_content(self, body: Any) -> str:
        raise NotImplementedError

    def build_tools(self, body: Any) -> ToolRegistry:
        return ToolRegistry()

    def extract_session_token(self, body: Any) -> str:
        return getattr(body, "session_token", "")

    def post_process(self, rationale: str, body: Any) -> dict:
        return {}

    # ── Pipeline ────────────────────────────────────────────────────────
    def _build_context(
        self, body: Any, prior_state: dict | None
    ) -> AgentContext:
        # Compose system + user text via ContextManager — but bypass the
        # clinical-specific build() and assemble messages directly.
        from app.agent.context_manager import compress_prior

        budget = self.context_manager.token_budget - self.context_manager._tokens(
            self.SYSTEM_PROMPT
        )

        parts: list[str] = []
        if prior_state and prior_state.get("last_summary"):
            compressed = compress_prior(prior_state["last_summary"])
            parts.append(f"[Prior session summary]\n{compressed}\n---")

        parts.append(self.build_user_content(body))
        user_content = "\n".join(parts)

        return AgentContext(
            system_prompt=self.SYSTEM_PROMPT,
            messages=[
                {"role": "system", "content": self.SYSTEM_PROMPT},
                {"role": "user", "content": user_content},
            ],
            budget_used=self.context_manager._tokens(user_content),
            budget_total=self.context_manager.token_budget,
        )

    async def run(self, body: Any, session_id: str | None = None) -> dict:
        """
        Execute the persona ETCSLV pipeline. Returns a dict the route layer
        can pass to the persona's Pydantic output model.
        """
        if session_id is None:
            session_id = str(uuid.uuid4())

        start_ms = int(time.time() * 1000)
        session_token = self.extract_session_token(body)

        await self.lifecycle.on_start(
            type("Body", (), {"case_token": session_token, "age_band": None, "gender": None})(),
            session_id,
        )

        try:
            prior_state = await self.state_store.get(session_token) if session_token else None
            context = self._build_context(body, prior_state)
            context.session_id = session_id

            registry = self.build_tools(body)
            tools = registry.resolve({"context": context})

            loop_result = await self.execution_loop.run(
                context=context, tools=tools, lifecycle=self.lifecycle,
                session_id=session_id, ollama_url=self.ollama_url, model=self.model,
                body=body,
            )

            vr = self.verification.validate(loop_result.rationale, body)

            citations: list[str] = []
            for tr in loop_result.tool_results:
                citations.extend(tr.get("citations", []))

            extras = self.post_process(loop_result.rationale, body)

            result = {
                "model_id": loop_result.model_id,
                "prompt_hash": hashlib.sha256(
                    context.messages[-1]["content"].encode()
                ).hexdigest(),
                "rationale": loop_result.rationale,
                "confidence": vr.confidence,
                "requires_human_review": vr.requires_review,
                "verification_issues": vr.issues,
                "_tool_count": len(loop_result.tool_results),
                "_iterations": loop_result.iterations,
                "_citations": citations[:5],
                **extras,
            }

            if session_token:
                await self.state_store.put(session_token, {
                    "rationale": loop_result.rationale,
                    "confidence": vr.confidence,
                    "escalation_pending": result.get("escalation_pending", False),
                })

            duration_ms = int(time.time() * 1000) - start_ms
            await self.lifecycle.on_complete(result, session_id, duration_ms)
            return result

        except Exception as exc:
            await self.lifecycle.on_error(str(exc), session_id)
            raise
