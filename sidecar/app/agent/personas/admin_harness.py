"""
Admin Agent Harness — answers owner questions about administrative health
(revenue, discounts, FBR compliance, payout integrity).

Confidence floor: 0.55. Below this floor the verification gate blocks
recommendations and substitutes a "Manual review required" notice.
"""
from __future__ import annotations

import re
from typing import Any

from app.agent.personas.base import PersonaHarness
from app.agent.personas.admin_tools import (
    make_discount_risk_tool,
    make_fbr_status_tool,
    make_payout_audit_tool,
    make_revenue_anomaly_tool,
    make_revenue_leakage_tool,
)
from app.agent.tool_registry import ToolRegistry
from app.agent.verification_interface import VerificationInterface


ADMIN_SYSTEM_PROMPT = (
    "You are an administrative AI assistant for Aviva HealthCare clinic.\n"
    "You surface data-driven findings and recommend actions — you do not make clinical decisions.\n"
    "Strict rules:\n"
    "1. Never include patient names, CNICs, phone numbers, or personal identifiers.\n"
    "2. Reference financial data by period and category only — do not list individual transaction IDs.\n"
    "3. Every recommendation must include priority (Critical/High/Medium/Low), the owner action, and a deadline.\n"
    "4. Output EXACTLY these markdown sections:\n"
    "   ## FINDING\n"
    "   (one sentence describing the data-driven observation)\n"
    "   ## EVIDENCE\n"
    "   (bullet points referencing the tool results)\n"
    "   ## RISK\n"
    "   Critical|High|Medium|Low — one sentence rationale\n"
    "   ## ACTION ITEMS\n"
    "   1. [Priority] Action — Owner — Deadline\n"
    "   ## CONFIDENCE\n"
    "   [overall: low|medium|high] — one sentence rationale\n"
    "5. If data is insufficient, state that explicitly under EVIDENCE — never guess."
)


class AdminVerification(VerificationInterface):
    """Admin-specific gates: confidence floor, financial-amount redaction."""

    REQUIRED = ["## FINDING", "## EVIDENCE", "## RISK", "## ACTION ITEMS", "## CONFIDENCE"]
    AMOUNT_PATTERN = re.compile(
        r"\b(?:PKR|Rs\.?|₨)\s?\d[\d,]*(?:\.\d+)?\b", re.IGNORECASE
    )

    def __init__(self) -> None:
        super().__init__(
            required_sections=self.REQUIRED,
            min_length=200,
            confidence_floor=0.55,
        )

    def validate(self, rationale, body=None):
        result = super().validate(rationale, body)
        if self.AMOUNT_PATTERN.search(rationale):
            result.issues.append("Financial amount detected in admin output (should be redacted)")
            result.passed = False
        return result


class AdminAgentHarness(PersonaHarness):
    SYSTEM_PROMPT = ADMIN_SYSTEM_PROMPT

    def __init__(self) -> None:
        super().__init__(agent="admin", verification=AdminVerification())

    def build_user_content(self, body: Any) -> str:
        parts = [
            f"Query type: {body.query_type}",
            f"Period: last {body.period_days} day(s)",
        ]
        if body.custom_question:
            parts.append(f"\nOwner question:\n{body.custom_question}")
        parts.append(
            "\nUse the tool results above as your sole evidence base. "
            "If a tool returned 'insufficient data', acknowledge that "
            "explicitly under EVIDENCE."
        )
        return "\n".join(parts)

    def build_tools(self, body: Any) -> ToolRegistry:
        registry = ToolRegistry()
        qt = getattr(body, "query_type", "general")
        # "general" runs all four tools; focused types run only the relevant tool(s)
        # so the model receives targeted evidence rather than the same full set every time.
        if qt in ("revenue_anomaly", "general"):
            registry.register(make_revenue_anomaly_tool(body.period_days))
        if qt in ("discount_risk", "general"):
            registry.register(make_discount_risk_tool(body.period_days))
        if qt in ("fbr_status", "general"):
            registry.register(make_fbr_status_tool(body.period_days))
        if qt in ("payout_audit", "general"):
            registry.register(make_payout_audit_tool(body.period_days))
        if qt in ("revenue_leakage", "general"):
            registry.register(make_revenue_leakage_tool(body.period_days))
        return registry

    def post_process(self, rationale: str, body: Any) -> dict:
        priority = "Medium"
        m = re.search(r"##\s*RISK\s*\n\s*(Critical|High|Medium|Low)", rationale, re.IGNORECASE)
        if m:
            priority = m.group(1).capitalize()
        actions: list[str] = []
        m2 = re.search(r"##\s*ACTION ITEMS\s*\n(.+?)(?=\n##\s|\Z)", rationale, re.DOTALL | re.IGNORECASE)
        if m2:
            for line in m2.group(1).strip().splitlines():
                line = line.strip()
                if line and re.match(r"^\d+\.", line):
                    actions.append(line)
        return {"priority": priority, "action_items": actions[:10]}
