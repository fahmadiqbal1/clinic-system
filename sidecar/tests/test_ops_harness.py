"""
Phase 8C regression — Ops persona harness.

Confirms:
  1. Ops schemas accept valid input
  2. OpsVerification rejects currency amounts (currency is admin-only)
  3. post_process extracts urgency and critical_items
  4. Critical items always trigger escalation_pending=True
  5. Required-section gate enforces the 5 ops sections
"""
from __future__ import annotations

import pytest
from pydantic import ValidationError

from app.agent.harness_factory import HarnessFactory
from app.agent.personas.ops_harness import OpsAgentHarness, OpsVerification
from app.schemas.ops import OpsAnalysisInput


def test_ops_input_rejects_invalid_domain():
    with pytest.raises(ValidationError):
        OpsAnalysisInput(session_token="a" * 64, domain="not-a-domain")


def test_ops_verification_blocks_currency():
    vi = OpsVerification()
    rationale = (
        "## STATUS\nWarning\n## CRITICAL ITEMS\n- None\n"
        "## ACTION ITEMS\n1. [Warning] Restock — Pharmacy — Tomorrow\n"
        "## EVIDENCE\n- Last reorder cost PKR 5000\n"
        "## CONFIDENCE\n[overall: medium] — fine"
    )
    vr = vi.validate(rationale)
    assert any("Currency" in i for i in vr.issues)
    assert vr.passed is False


def test_ops_post_process_extracts_urgency_and_critical():
    h = OpsAgentHarness()
    rationale = (
        "## STATUS\nCritical\n"
        "## CRITICAL ITEMS\n- Paracetamol 500mg\n- Insulin 100IU\n"
        "## ACTION ITEMS\n1. [Critical] Reorder — Pharmacy — Today\n"
        "## EVIDENCE\n- inventory_velocity flagged 2 stock-outs\n"
        "## CONFIDENCE\n[overall: high] — clear"
    )
    extras = h.post_process(rationale, body=None)
    assert extras["urgency"] == "Critical"
    assert "Paracetamol 500mg" in extras["critical_items"]
    assert extras["escalation_pending"] is True


def test_ops_no_critical_items_no_escalation():
    h = OpsAgentHarness()
    rationale = (
        "## STATUS\nHealthy\n"
        "## CRITICAL ITEMS\nNone\n"
        "## ACTION ITEMS\n1. [Info] Continue monitoring — Pharmacy — N/A\n"
        "## EVIDENCE\n- All items above minimum\n"
        "## CONFIDENCE\n[overall: high] — clean"
    )
    extras = h.post_process(rationale, body=None)
    assert extras["escalation_pending"] is False


def test_ops_required_sections_strict():
    vi = OpsVerification()
    rationale = (
        "## STATUS\nHealthy\n"
        # missing CRITICAL ITEMS, ACTION ITEMS, EVIDENCE, CONFIDENCE
    )
    vr = vi.validate(rationale)
    assert any("Missing or thin sections" in i for i in vr.issues)
    assert vr.passed is False
