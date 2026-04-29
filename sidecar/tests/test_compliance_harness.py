"""
Phase 8D regression — Compliance persona harness.

Confirms:
  1. Compliance schemas accept valid input + reject invalid scope
  2. ComplianceVerification enforces 0.85 confidence floor
  3. ComplianceVerification requires evidence references in output
  4. AUDIT STATUS = FAIL forces post_process status to NON_COMPLIANT
  5. NON_COMPLIANT or High PHI risk forces escalation_pending=True
"""
from __future__ import annotations

import pytest
from pydantic import ValidationError

from app.agent.personas.compliance_harness import (
    ComplianceAgentHarness,
    ComplianceVerification,
)
from app.schemas.compliance import ComplianceAnalysisInput


def test_compliance_input_rejects_invalid_scope():
    ComplianceAnalysisInput(session_token="a" * 64, scope="full")
    with pytest.raises(ValidationError):
        ComplianceAnalysisInput(session_token="a" * 64, scope="not-a-scope")


def test_compliance_verification_enforces_0_85_floor():
    vi = ComplianceVerification()
    rationale = (
        "## AUDIT STATUS\nPASS — chunk=500 verified\n"
        "## PHI EXPOSURE RISK\nLow\n"
        "## EVIDENCE GAPS\n- soc2-2026-04.zip current\n"
        "## CERTIFICATION READINESS\nCOMPLIANT — no issues\n"
        "## CONFIDENCE\n[overall: medium] — fine"
    )
    vr = vi.validate(rationale)
    assert any("below floor 0.85" in i for i in vr.issues)


def test_compliance_verification_requires_evidence_reference():
    vi = ComplianceVerification()
    rationale = (
        "## AUDIT STATUS\nPASS\n"
        "## PHI EXPOSURE RISK\nLow\n"
        "## EVIDENCE GAPS\n- none\n"
        "## CERTIFICATION READINESS\nCOMPLIANT\n"
        "## CONFIDENCE\n[overall: high] — clean"
    )
    vr = vi.validate(rationale)
    assert any("evidence reference" in i.lower() for i in vr.issues)


def test_compliance_post_process_audit_fail_forces_non_compliant():
    h = ComplianceAgentHarness()
    rationale = (
        "## AUDIT STATUS\nFAIL — chain mismatch at row 42\n"
        "## PHI EXPOSURE RISK\nLow\n"
        "## EVIDENCE GAPS\n- audit:verify-chain reported failure\n"
        "## CERTIFICATION READINESS\nREQUIRES_REVIEW — model could not parse\n"
        "## CONFIDENCE\n[overall: high] — chain failure is unambiguous"
    )
    extras = h.post_process(rationale, body=None)
    assert extras["status"] == "NON_COMPLIANT"
    assert extras["escalation_pending"] is True


def test_compliance_high_phi_risk_forces_escalation():
    h = ComplianceAgentHarness()
    rationale = (
        "## AUDIT STATUS\nPASS — chunk=500 verified\n"
        "## PHI EXPOSURE RISK\nHigh — u17 accessed 250 patient records in one hour\n"
        "## EVIDENCE GAPS\n- soc2-2026-04.zip current\n"
        "## CERTIFICATION READINESS\nREQUIRES_REVIEW — investigate u17 activity\n"
        "## CONFIDENCE\n[overall: high] — phi_access_scan tool flagged anomaly"
    )
    extras = h.post_process(rationale, body=None)
    assert extras["escalation_pending"] is True
    assert extras["status"] == "REQUIRES_REVIEW"
