"""
HarnessFactory — single source of singleton harness instances.

Each persona is constructed once at app startup and reused across requests.
Tests can call factory.reset() to drop singletons between cases.
"""
from __future__ import annotations

from typing import Optional

from app.agent.harness import AgentHarness
from app.agent.personas.admin_harness import AdminAgentHarness
from app.agent.personas.compliance_harness import ComplianceAgentHarness
from app.agent.personas.ops_harness import OpsAgentHarness


class HarnessFactory:
    _clinical: Optional[AgentHarness] = None
    _admin: Optional[AdminAgentHarness] = None
    _ops: Optional[OpsAgentHarness] = None
    _compliance: Optional[ComplianceAgentHarness] = None

    @classmethod
    def clinical(cls) -> AgentHarness:
        if cls._clinical is None:
            cls._clinical = AgentHarness(agent="clinical")
        return cls._clinical

    @classmethod
    def admin(cls) -> AdminAgentHarness:
        if cls._admin is None:
            cls._admin = AdminAgentHarness()
        return cls._admin

    @classmethod
    def ops(cls) -> OpsAgentHarness:
        if cls._ops is None:
            cls._ops = OpsAgentHarness()
        return cls._ops

    @classmethod
    def compliance(cls) -> ComplianceAgentHarness:
        if cls._compliance is None:
            cls._compliance = ComplianceAgentHarness()
        return cls._compliance

    @classmethod
    def reset(cls) -> None:
        """Test helper — clears singletons."""
        cls._clinical = None
        cls._admin = None
        cls._ops = None
        cls._compliance = None

    @classmethod
    def status(cls) -> dict:
        """ETCSLV pillar health summary across all instantiated harnesses."""
        out: dict = {}
        for label, h in (
            ("clinical", cls._clinical),
            ("admin", cls._admin),
            ("ops", cls._ops),
            ("compliance", cls._compliance),
        ):
            if h is None:
                out[label] = {"instantiated": False}
                continue
            try:
                tool_count = len(h.build_tools(_DummyBody(label)).schemas)
            except Exception:
                tool_count = -1
            out[label] = {
                "instantiated": True,
                "agent": h.agent,
                "model": h.model,
                "redis_backed": h.state_store.is_redis,
                "hook_count": len(h.lifecycle._hooks),
                "tool_count": tool_count,
                "verification_class": type(h.verification).__name__,
            }
        return out


class _DummyBody:
    """Minimal stand-in so build_tools() can run during status() without a real request."""
    def __init__(self, persona: str) -> None:
        self.persona = persona
        self.case_token = "0" * 64
        self.session_token = "0" * 64
        self.age_band = "0-0"
        self.gender = "unknown"
        self.medications: list[str] = []
        self.vitals = None
        self.lab_results: list = []
        self.radiology: list = []
        self.custom_question = None
        self.period_days = 7
        self.query_type = "general"
        self.domain = "general"
        self.scope = "full"
