"""
Compliance Agent Harness — strictest persona.

Confidence floor: 0.85. Any PHI access anomaly OR audit chain failure
forces escalation_pending=True regardless of model output.
"""
from __future__ import annotations

import re
from typing import Any

from app.agent.personas.base import PersonaHarness
from app.agent.personas.compliance_tools import (
    make_audit_chain_tool,
    make_evidence_gap_tool,
    make_flag_snapshot_tool,
    make_phi_access_scan_tool,
)
from app.agent.tool_registry import ToolRegistry
from app.agent.verification_interface import VerificationInterface


COMPLIANCE_SYSTEM_PROMPT = (
    "You are a compliance AI assistant for Aviva HealthCare clinic.\n"
    "You verify the audit chain, scan for PHI access anomalies, and report SOC2 evidence gaps.\n"
    "Strict rules:\n"
    "1. Never include patient names, CNICs, phones, or any PHI.\n"
    "2. Every finding MUST cite specific evidence (audit log IDs, file names, or counts).\n"
    "3. Output EXACTLY these markdown sections (mandatory order):\n"
    "   ## AUDIT STATUS\n"
    "   (PASS|FAIL — one sentence based on audit_chain_verify tool)\n"
    "   ## PHI EXPOSURE RISK\n"
    "   (Low|Medium|High — based on phi_access_scan tool)\n"
    "   ## EVIDENCE GAPS\n"
    "   (bullets — files / windows missing)\n"
    "   ## CERTIFICATION READINESS\n"
    "   COMPLIANT|REQUIRES_REVIEW|NON_COMPLIANT — one sentence rationale\n"
    "   ## CONFIDENCE\n"
    "   [overall: low|medium|high] — rationale\n"
    "4. If audit_chain_verify reports FAIL, CERTIFICATION READINESS MUST be NON_COMPLIANT.\n"
    "5. State 'data unavailable' explicitly when a tool returned an error."
)


class ComplianceVerification(VerificationInterface):
    REQUIRED = [
        "## AUDIT STATUS",
        "## PHI EXPOSURE RISK",
        "## EVIDENCE GAPS",
        "## CERTIFICATION READINESS",
        "## CONFIDENCE",
    ]

    def __init__(self) -> None:
        super().__init__(
            required_sections=self.REQUIRED,
            min_length=200,
            confidence_floor=0.85,
        )

    def validate(self, rationale, body=None):
        result = super().validate(rationale, body)
        # Evidence-citation gate: at least one numeric/file reference required
        if not re.search(r"(\d{2,}|\.zip|\.json|audit:verify-chain|user_id)", rationale, re.IGNORECASE):
            result.issues.append("No specific evidence reference found")
            result.passed = False
        return result


class ComplianceAgentHarness(PersonaHarness):
    SYSTEM_PROMPT = COMPLIANCE_SYSTEM_PROMPT

    def __init__(self) -> None:
        super().__init__(agent="compliance", verification=ComplianceVerification())

    def build_user_content(self, body: Any) -> str:
        parts = [
            f"Scope: {body.scope}",
            f"Period: last {body.period_days} day(s)",
        ]
        if body.custom_question:
            parts.append(f"\nAuditor question:\n{body.custom_question}")
        parts.append(
            "\nUse the tool results above as your evidence base. "
            "Cite specific log IDs / file names / user_ids."
        )
        return "\n".join(parts)

    def build_tools(self, body: Any) -> ToolRegistry:
        registry = ToolRegistry()
        registry.register(make_audit_chain_tool())
        registry.register(make_phi_access_scan_tool(body.period_days))
        registry.register(make_flag_snapshot_tool())
        registry.register(make_evidence_gap_tool())
        return registry

    def post_process(self, rationale: str, body: Any) -> dict:
        status = "REQUIRES_REVIEW"
        m = re.search(
            r"##\s*CERTIFICATION READINESS\s*\n\s*(COMPLIANT|REQUIRES_REVIEW|NON_COMPLIANT)",
            rationale, re.IGNORECASE,
        )
        if m:
            status = m.group(1).upper()

        # Tool-driven escalation: any "FAIL" in audit status block → NON_COMPLIANT
        audit_block = re.search(r"##\s*AUDIT STATUS\s*\n(.+?)(?=\n##\s|\Z)", rationale, re.DOTALL | re.IGNORECASE)
        if audit_block and "FAIL" in audit_block.group(1).upper():
            status = "NON_COMPLIANT"

        # Evidence references — anything that looks like a numeric ID, file, or user_id
        evidence_refs = list(set(re.findall(
            r"(?:user_id=\w+|u\d+|[A-Za-z0-9_\-]+\.zip|[A-Za-z0-9_\-]+\.json|audit:verify-chain|chunk=\d+)",
            rationale,
        )))[:10]

        # PHI exposure / audit fail force escalation
        escalation = (status == "NON_COMPLIANT") or bool(
            re.search(r"##\s*PHI EXPOSURE RISK\s*\n\s*High", rationale, re.IGNORECASE)
        )

        return {
            "status": status,
            "evidence_refs": evidence_refs,
            "escalation_pending": escalation,
        }
