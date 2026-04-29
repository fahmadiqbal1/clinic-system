"""
Phase 8B regression — Admin persona harness.

Confirms:
  1. Admin schemas accept valid input and reject malformed session_token
  2. AdminVerification rejects financial amounts in output
  3. AdminVerification enforces the 0.55 confidence floor
  4. post_process extracts priority + action items from rationale
  5. HarnessFactory.admin() returns a singleton
"""
from __future__ import annotations

import pytest
from pydantic import ValidationError

from app.agent.harness_factory import HarnessFactory
from app.agent.personas.admin_harness import AdminAgentHarness, AdminVerification
from app.schemas.admin import AdminAnalysisInput


def test_admin_input_validates_session_token():
    AdminAnalysisInput(session_token="a" * 64, query_type="general")
    with pytest.raises(ValidationError):
        AdminAnalysisInput(session_token="not-hex", query_type="general")


def test_admin_verification_blocks_financial_amounts():
    vi = AdminVerification()
    rationale = (
        "## FINDING\nRevenue was PKR 12,000 yesterday.\n"
        "## EVIDENCE\n- pkr 12,000 collected\n"
        "## RISK\nLow — within normal range\n"
        "## ACTION ITEMS\n1. [Low] Continue monitoring — Owner — EOD\n"
        "## CONFIDENCE\n[overall: medium] — solid"
    )
    vr = vi.validate(rationale)
    assert any("Financial amount" in i for i in vr.issues)
    assert vr.passed is False


def test_admin_verification_confidence_floor_at_055():
    vi = AdminVerification()
    rationale = (
        "## FINDING\nNoise.\n## EVIDENCE\n- nothing\n## RISK\nLow\n"
        "## ACTION ITEMS\n1. [Low] Skip — Owner — N/A\n"
        "## CONFIDENCE\n[overall: low] — uncertain"
    )
    vr = vi.validate(rationale)
    assert any("Confidence" in i and "below floor" in i for i in vr.issues)


def test_admin_post_process_extracts_priority_and_actions():
    h = AdminAgentHarness()
    rationale = (
        "## FINDING\nDiscount concentration noted.\n"
        "## EVIDENCE\n- u17 had 12 requests\n"
        "## RISK\nHigh — unusual pattern\n"
        "## ACTION ITEMS\n1. [High] Audit u17 — Owner — Tomorrow\n"
        "2. [Medium] Tighten policy — Owner — Next week\n"
        "## CONFIDENCE\n[overall: medium] — clear data"
    )
    extras = h.post_process(rationale, body=None)
    assert extras["priority"] == "High"
    assert len(extras["action_items"]) == 2


def test_harness_factory_admin_singleton():
    HarnessFactory.reset()
    a1 = HarnessFactory.admin()
    a2 = HarnessFactory.admin()
    assert a1 is a2
    assert a1.agent == "admin"
