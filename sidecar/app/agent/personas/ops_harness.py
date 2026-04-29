"""
Operations Agent Harness — inventory, procurement, expense, queue health.

Critical inventory findings ALWAYS escalate (regardless of confidence)
because a stock-out is a hard operational signal that does not depend on
model judgment.
"""
from __future__ import annotations

import re
from typing import Any

from app.agent.personas.base import PersonaHarness
from app.agent.personas.ops_tools import (
    make_expense_category_tool,
    make_inventory_velocity_tool,
    make_procurement_recommendation_tool,
    make_queue_health_tool,
)
from app.agent.tool_registry import ToolRegistry
from app.agent.verification_interface import VerificationInterface


OPS_SYSTEM_PROMPT = (
    "You are an operations AI assistant for Aviva HealthCare clinic.\n"
    "You report on inventory, procurement, expenses, and queue health using tool data.\n"
    "Strict rules:\n"
    "1. NEVER include currency amounts (PKR, Rs, ₨). Inventory and ops are quantity-only.\n"
    "2. Reference inventory items by NAME (not internal IDs).\n"
    "3. Output EXACTLY these markdown sections:\n"
    "   ## STATUS\n"
    "   (one sentence overview — Critical / Warning / Healthy)\n"
    "   ## CRITICAL ITEMS\n"
    "   (bulleted list of any critical items by name; or 'None')\n"
    "   ## ACTION ITEMS\n"
    "   1. [Urgency: Critical|Warning|Info] Action — Owner — Deadline\n"
    "   ## EVIDENCE\n"
    "   (bullets summarising tool outputs)\n"
    "   ## CONFIDENCE\n"
    "   [overall: low|medium|high] — rationale\n"
    "4. If a tool returned 'unavailable', say so under EVIDENCE — never guess."
)


class OpsVerification(VerificationInterface):
    REQUIRED = ["## STATUS", "## CRITICAL ITEMS", "## ACTION ITEMS", "## EVIDENCE", "## CONFIDENCE"]
    AMOUNT_PATTERN = re.compile(
        r"\b(?:PKR|Rs\.?|₨)\s?\d[\d,]*(?:\.\d+)?\b", re.IGNORECASE
    )

    def __init__(self) -> None:
        super().__init__(
            required_sections=self.REQUIRED,
            min_length=180,
            confidence_floor=0.50,
        )

    def validate(self, rationale, body=None):
        result = super().validate(rationale, body)
        if self.AMOUNT_PATTERN.search(rationale):
            result.issues.append("Currency amount in ops output (currency belongs in admin agent)")
            result.passed = False
        return result


class OpsAgentHarness(PersonaHarness):
    SYSTEM_PROMPT = OPS_SYSTEM_PROMPT

    def __init__(self) -> None:
        super().__init__(agent="ops", verification=OpsVerification())

    def build_user_content(self, body: Any) -> str:
        parts = [
            f"Domain: {body.domain}",
            f"Period: last {body.period_days} day(s)",
        ]
        if body.custom_question:
            parts.append(f"\nOperator question:\n{body.custom_question}")
        parts.append(
            "\nUse the tool results above as your sole evidence base. "
            "Surface CRITICAL items first."
        )
        return "\n".join(parts)

    def build_tools(self, body: Any) -> ToolRegistry:
        registry = ToolRegistry()
        # Always pre-fetch ops signals; model selects relevant ones.
        registry.register(make_inventory_velocity_tool())
        registry.register(make_procurement_recommendation_tool())
        registry.register(make_expense_category_tool(body.period_days))
        registry.register(make_queue_health_tool())
        return registry

    def post_process(self, rationale: str, body: Any) -> dict:
        urgency = "Info"
        m = re.search(r"##\s*STATUS\s*\n\s*(Critical|Warning|Healthy|Info)", rationale, re.IGNORECASE)
        if m:
            mapped = {"healthy": "Info"}.get(m.group(1).lower(), m.group(1).capitalize())
            urgency = mapped
        # Critical items list
        critical_items: list[str] = []
        m2 = re.search(r"##\s*CRITICAL ITEMS\s*\n(.+?)(?=\n##\s|\Z)", rationale, re.DOTALL | re.IGNORECASE)
        if m2:
            for line in m2.group(1).strip().splitlines():
                line = line.strip("-• ").strip()
                if line and line.lower() not in {"none", "n/a", ""}:
                    critical_items.append(line)
        # Action items
        actions: list[str] = []
        m3 = re.search(r"##\s*ACTION ITEMS\s*\n(.+?)(?=\n##\s|\Z)", rationale, re.DOTALL | re.IGNORECASE)
        if m3:
            for line in m3.group(1).strip().splitlines():
                line = line.strip()
                if line and re.match(r"^\d+\.", line):
                    actions.append(line)
        # Critical inventory ALWAYS escalates regardless of confidence
        escalation = bool(critical_items)
        return {
            "urgency": urgency,
            "critical_items": critical_items[:10],
            "action_items": actions[:10],
            "escalation_pending": escalation,
        }
