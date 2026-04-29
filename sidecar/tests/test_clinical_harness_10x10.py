"""
Phase 8A — Clinical harness 10/10 regression tests.

One regression test per pillar covering the upgrades that took each pillar
from its Phase 7 score to 10/10. These tests should never go stale silently —
if a future refactor regresses a pillar, the matching test fails first.

Pillars:
  E — retry-on-low-confidence in ExecutionLoop
  T — vital_alert + medication_safety tools wired in AgentHarness.build_tools
  C — system_prompt is injectable + compress_prior shrinks long summaries
  S — namespaced StateStore + Redis-vs-in-memory selection by env var
  L — prometheus_metrics_hook + low_confidence_alert_hook auto-registered
  V — passed=False when issues exist + hallucinated drug heuristic + section score
"""
from __future__ import annotations

import os
from unittest.mock import AsyncMock, patch

import pytest

from app.agent.clinical_tools import (
    make_medication_safety_tool,
    make_vital_alert_tool,
)
from app.agent.context_manager import (
    CLINICAL_SYSTEM_PROMPT,
    ContextManager,
    compress_prior,
)
from app.agent.execution_loop import ExecutionLoop
from app.agent.harness import AgentHarness
from app.agent.lifecycle_hooks import LifecycleHooks
from app.agent.state_store import StateStore
from app.agent.verification_interface import VerificationInterface
from app.schemas.medical import ConsultInput, VitalSet


# ── E: ExecutionLoop retry-on-low-confidence ──────────────────────────────────

@pytest.mark.asyncio
async def test_E_execution_loop_retries_when_confidence_below_threshold():
    """
    When verification reports confidence < retry_threshold, the loop must
    issue a second model call (iterations == 2). When the first response
    is high-confidence it must NOT retry (iterations == 1).
    """
    body = ConsultInput(case_token="e" * 64, age_band="30-34", gender="male")
    cm = ContextManager()
    ctx = cm.build(body)

    high_conf = (
        "## ASSESSMENT\nClear case.\n## DIFFERENTIALS\n1. X — y — Confidence: high\n"
        "## RECOMMENDATIONS\nDo Z.\n## CONFIDENCE\n[overall: high] — solid."
    )
    low_conf = (
        "## ASSESSMENT\nInsufficient data.\n## DIFFERENTIALS\n1. ? — uncertain — Confidence: low\n"
        "## RECOMMENDATIONS\nMore tests.\n## CONFIDENCE\n[overall: low] — uncertain."
    )

    vi = VerificationInterface()
    loop = ExecutionLoop(verification=vi, retry_threshold=0.40)
    hooks = LifecycleHooks()

    # Case A: low confidence first → retry
    with patch.object(loop, "_call_ollama", new=AsyncMock(side_effect=[low_conf, high_conf])):
        result = await loop.run(
            context=ctx, tools=[], lifecycle=hooks, session_id="s1",
            ollama_url="x", model="m", body=body,
        )
    assert result.iterations == 2

    # Case B: high confidence first → no retry
    with patch.object(loop, "_call_ollama", new=AsyncMock(return_value=high_conf)):
        result2 = await loop.run(
            context=ctx, tools=[], lifecycle=hooks, session_id="s2",
            ollama_url="x", model="m", body=body,
        )
    assert result2.iterations == 1


# ── T: vital_alert + medication_safety tools ──────────────────────────────────

@pytest.mark.asyncio
async def test_T_vital_alert_flags_critical_bp_and_medication_safety_fails_open():
    """
    vital_alert must produce a 'critical' finding for BP 200/130.
    medication_safety must degrade to empty answer when RAGFlow is down,
    not raise.
    """
    body = ConsultInput(
        case_token="a" * 64,
        age_band="60-64",
        gender="male",
        vitals=VitalSet(bp_systolic=200, bp_diastolic=130, heart_rate=110),
        medications=["Aspirin 100mg", "Warfarin 5mg"],
    )

    vital_tool = make_vital_alert_tool(body)
    res = await vital_tool.invoke({})
    assert res["findings"]
    assert any(f["severity"] == "critical" for f in res["findings"])
    assert "Hypertensive crisis" in res["answer"]

    med_tool = make_medication_safety_tool(body)
    with patch("app.agent.clinical_tools.ragflow.query", new=AsyncMock(side_effect=RuntimeError("ragflow down"))):
        out = await med_tool.invoke({})
    assert out["answer"] == ""
    assert out["citations"] == []


# ── C: injectable system_prompt + compress_prior ──────────────────────────────

def test_C_context_manager_uses_injected_system_prompt_and_compresses_prior():
    """
    ContextManager(system_prompt=...) must use the supplied prompt instead
    of the clinical default, and compress_prior must shrink long summaries
    while preserving short ones unchanged.
    """
    custom_prompt = "You are an administrative assistant. Output only JSON."
    cm = ContextManager(system_prompt=custom_prompt)
    body = ConsultInput(case_token="c" * 64, age_band="20-24", gender="female")
    ctx = cm.build(body)

    assert ctx.messages[0]["content"] == custom_prompt
    assert "MedGemma" not in ctx.messages[0]["content"]

    # compress_prior: short input is returned unchanged
    short = "## ASSESSMENT\nbrief"
    assert compress_prior(short) == short

    # compress_prior: long input is shrunk and keeps ASSESSMENT + CONFIDENCE
    long_summary = (
        "## ASSESSMENT\n" + ("padding text. " * 30)
        + "\n## DIFFERENTIALS\n" + ("noise. " * 50)
        + "\n## RECOMMENDATIONS\n" + ("more noise. " * 50)
        + "\n## CONFIDENCE\n[overall: medium] — fine."
    )
    compressed = compress_prior(long_summary)
    assert len(compressed) < len(long_summary)
    assert "ASSESSMENT" in compressed
    assert "CONFIDENCE" in compressed
    # DIFFERENTIALS noise is dropped
    assert "DIFFERENTIALS" not in compressed


# ── S: namespaced StateStore + backend selection ──────────────────────────────

@pytest.mark.asyncio
async def test_S_state_store_namespacing_and_backend_selection(monkeypatch):
    """
    Two stores with different namespaces must NOT collide on the same case
    token (in-memory backend). Backend selection: REDIS_URL absent →
    is_redis False; key format is etcslv:<namespace>:<token>.
    """
    monkeypatch.delenv("REDIS_URL", raising=False)

    clinical = StateStore(namespace="clinical")
    admin = StateStore(namespace="admin")

    assert clinical.is_redis is False
    assert admin.is_redis is False
    assert clinical._key("abc") == "etcslv:clinical:abc"
    assert admin._key("abc") == "etcslv:admin:abc"

    token = "s" * 64
    await clinical.put(token, {"rationale": "clinical text", "confidence": 0.7})
    await admin.put(token, {"rationale": "admin text", "confidence": 0.5})

    cs = await clinical.get(token)
    ad = await admin.get(token)
    assert cs is not None and "clinical" in cs["last_summary"]
    assert ad is not None and "admin" in ad["last_summary"]
    # Each namespace tracked exactly one consultation
    assert cs["consultation_count"] == 1
    assert ad["consultation_count"] == 1


# ── L: lifecycle hooks auto-registered + metrics labels ───────────────────────

@pytest.mark.asyncio
async def test_L_harness_registers_metrics_and_alert_hooks(monkeypatch):
    """
    AgentHarness construction must register the three hooks
    (logging + metrics + low-confidence alert). Firing on_complete with
    confidence below threshold + a configured webhook must POST to it;
    without the webhook env var the hook must silently skip.
    """
    monkeypatch.delenv("CLINIC_ALERT_WEBHOOK_URL", raising=False)
    h = AgentHarness(agent="test-clinical")
    # 3 hooks: default_logging, prometheus_metrics, low_confidence_alert
    assert len(h.lifecycle._hooks) == 3
    names = [getattr(fn, "__name__", "") for fn in h.lifecycle._hooks]
    assert "default_logging_hook" in names
    assert "prometheus_metrics_hook" in names
    assert "low_confidence_alert_hook" in names

    # No webhook configured → on_complete with low confidence does NOT raise
    await h.lifecycle.on_complete(
        {"confidence": 0.20, "requires_human_review": True, "_tool_count": 0,
         "retrieval_citations": []},
        session_id="s-low",
        duration_ms=123,
    )

    # With webhook configured → POST is attempted
    monkeypatch.setenv("CLINIC_ALERT_WEBHOOK_URL", "http://example.invalid/webhook")
    with patch("app.agent.lifecycle_hooks.httpx.AsyncClient") as MockClient:
        instance = MockClient.return_value.__aenter__.return_value
        instance.post = AsyncMock(return_value=None)
        await h.lifecycle.on_complete(
            {"confidence": 0.20, "requires_human_review": True, "_tool_count": 0,
             "retrieval_citations": []},
            session_id="s-low-2",
            duration_ms=200,
        )
        instance.post.assert_awaited()


# ── V: passed-bug fix + hallucination heuristic + section score ───────────────

def test_V_passed_false_when_any_issue_and_hallucination_flagged():
    """
    Pre-fix: passed could be True even with hallucination/confidence-floor
    issues because the truthy check only inspected 'section'/'short' substrings.
    Post-fix: passed=False iff issues is non-empty.

    Hallucination: model output names 'Verylongfakedrug 200mg PO' but the
    input medications list does not contain it → flagged.
    """
    body = ConsultInput(
        case_token="b" * 64,
        age_band="50-54",
        gender="male",
        medications=["Aspirin 100mg"],
    )

    rationale = (
        "## ASSESSMENT\nPatient is stable but reports new symptom.\n"
        "## DIFFERENTIALS\n1. Angina — chest tightness — Confidence: medium\n"
        "## RECOMMENDATIONS\nStart Verylongfakedrug 200mg PO daily and monitor.\n"
        "## CONFIDENCE\n[overall: medium] — partial picture."
    )

    vi = VerificationInterface()
    vr = vi.validate(rationale, body)

    # Hallucination flagged
    assert "Verylongfakedrug" in vr.hallucinations
    # Issues is non-empty → passed must be False (the fix)
    assert vr.issues
    assert vr.passed is False
    # Section score is 1.0 here (all 3 required sections present with content)
    assert vr.section_score == 1.0
    # PHI gate did NOT trip (no quarantine)
    assert vr.phi_detected is False
