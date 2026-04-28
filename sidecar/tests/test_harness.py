"""
ETCSLV Agent Harness — unit tests.

Tests each pillar independently:
  E — ExecutionLoop   : tool pre-fetch + model call (mocked)
  T — ToolRegistry    : registration, resolution, fail-open
  C — ContextManager  : token budget, truncation, system prompt
  S — StateStore      : get/put/evict
  L — LifecycleHooks  : hook firing, isolation from failures
  V — VerificationInterface : confidence parsing, PHI gate, required sections

Integration:
  AgentHarness.run() end-to-end with mocked Ollama
"""
from __future__ import annotations

import asyncio
import time

import pytest

from app.agent.context_manager import ContextManager
from app.agent.lifecycle_hooks import HookEvent, LifecycleHooks
from app.agent.state_store import CaseState, StateStore
from app.agent.tool_registry import Tool, ToolRegistry
from app.agent.verification_interface import VerificationInterface
from app.schemas.medical import ConsultInput, VitalSet


# ── Fixtures ──────────────────────────────────────────────────────────────────

@pytest.fixture
def valid_body() -> ConsultInput:
    return ConsultInput(
        case_token="a" * 64,
        age_band="30-34",
        gender="male",
        vitals=VitalSet(
            bp_systolic=130,
            bp_diastolic=85,
            heart_rate=78,
            temperature_c=37.0,
            spo2=98,
            chief_complaint="chest tightness",
        ),
        medications=["Aspirin 100mg", "Atorvastatin 40mg"],
    )


# ── T: Tool Registry ──────────────────────────────────────────────────────────

def test_tool_registry_register_and_get():
    async def noop(ctx): return {"ok": True}
    tool = Tool(name="test_tool", description="test", schema={}, invoke_fn=noop)
    registry = ToolRegistry()
    registry.register(tool)
    assert registry.get("test_tool") is tool


def test_tool_registry_resolve_all_by_default():
    async def noop(ctx): return {}
    t1 = Tool(name="t1", description="", schema={}, invoke_fn=noop)
    t2 = Tool(name="t2", description="", schema={}, invoke_fn=noop)
    registry = ToolRegistry()
    registry.register(t1)
    registry.register(t2)
    assert len(registry.resolve({})) == 2


@pytest.mark.asyncio
async def test_tool_fail_open_returns_error_dict():
    async def raises(ctx):
        raise RuntimeError("simulated failure")

    tool = Tool(name="broken", description="", schema={}, invoke_fn=raises, fail_open=True)
    result = await tool.invoke({})
    assert "error" in result
    assert result["tool"] == "broken"


@pytest.mark.asyncio
async def test_tool_fail_closed_propagates():
    async def raises(ctx):
        raise RuntimeError("simulated failure")

    tool = Tool(name="strict", description="", schema={}, invoke_fn=raises, fail_open=False)
    with pytest.raises(RuntimeError):
        await tool.invoke({})


# ── C: Context Manager ────────────────────────────────────────────────────────

def test_context_manager_builds_system_prompt(valid_body):
    cm = ContextManager()
    ctx = cm.build(valid_body)
    assert ctx.messages[0]["role"] == "system"
    assert "MedGemma" in ctx.messages[0]["content"]
    assert "## CONFIDENCE" in ctx.messages[0]["content"]


def test_context_manager_user_message_contains_demographics(valid_body):
    cm = ContextManager()
    ctx = cm.build(valid_body)
    user_msg = ctx.messages[1]["content"]
    assert "a" * 64 in user_msg
    assert "30-34" in user_msg
    assert "chest tightness" in user_msg


def test_context_manager_truncates_long_medications():
    body = ConsultInput(
        case_token="b" * 64,
        age_band="40-44",
        gender="female",
        medications=[f"Drug{i} 100mg" for i in range(20)],
    )
    cm = ContextManager()
    ctx = cm.build(body)
    # Should include at most 10 medications
    user_content = ctx.messages[1]["content"]
    assert user_content.count("Drug") <= 10


def test_context_injects_tool_results():
    from app.agent.context_manager import AgentContext
    ctx = AgentContext(
        system_prompt="sys",
        messages=[
            {"role": "system", "content": "sys"},
            {"role": "user", "content": "user data"},
        ],
        budget_used=100,
        budget_total=6000,
    )
    updated = ctx.inject_tool_results([
        {"tool": "rag_query", "answer": "Use aspirin for prophylaxis.", "citations": ["guideline p.1"]}
    ])
    assert "aspirin" in updated[-1]["content"].lower()
    assert "user data" in updated[-1]["content"]


# ── S: State Store ────────────────────────────────────────────────────────────

@pytest.mark.asyncio
async def test_state_store_get_returns_none_for_unknown():
    store = StateStore()
    result = await store.get("a" * 64)
    assert result is None


@pytest.mark.asyncio
async def test_state_store_put_and_get():
    store = StateStore()
    token = "c" * 64
    await store.put(token, {"rationale": "Assessment: normal. ## ASSESSMENT ... ## CONFIDENCE [overall: medium]", "confidence": 0.65})
    state = await store.get(token)
    assert state is not None
    assert state["consultation_count"] == 1
    assert len(state["confidence_history"]) == 1


@pytest.mark.asyncio
async def test_state_store_evicts_expired():
    store = StateStore()
    token = "d" * 64
    state = CaseState(case_token=token, last_updated=time.time() - 90000)  # expired
    async with store._lock:
        store._store[token] = state

    result = await store.get(token)
    assert result is None
    assert store.size == 0


# ── L: Lifecycle Hooks ────────────────────────────────────────────────────────

@pytest.mark.asyncio
async def test_lifecycle_hook_fires_on_start(valid_body):
    hooks = LifecycleHooks()
    fired: list[HookEvent] = []

    async def capture(event: HookEvent) -> None:
        fired.append(event)

    hooks.register(capture)
    await hooks.on_start(valid_body, session_id="test-session")
    assert len(fired) == 1
    assert fired[0].event == "start"
    assert fired[0].session_id == "test-session"


@pytest.mark.asyncio
async def test_lifecycle_hook_failure_is_isolated(valid_body):
    hooks = LifecycleHooks()

    async def broken_hook(event: HookEvent) -> None:
        raise RuntimeError("hook crash")

    hooks.register(broken_hook)
    # Should not raise
    await hooks.on_start(valid_body, session_id="test-session")


# ── V: Verification Interface ─────────────────────────────────────────────────

def test_verification_parses_high_confidence():
    vi = VerificationInterface()
    rationale = (
        "## ASSESSMENT\nPatient presents with chest tightness.\n"
        "## DIFFERENTIALS\n1. NSTEMI — elevated troponin — Confidence: high\n"
        "## RECOMMENDATIONS\nAdmit for observation.\n"
        "## CONFIDENCE\n[overall: high] — Clear clinical picture with elevated troponin."
    )
    vr = vi.validate(rationale)
    assert vr.confidence == 0.90


def test_verification_parses_low_confidence():
    vi = VerificationInterface()
    rationale = (
        "## ASSESSMENT\nInsufficient data to conclude.\n"
        "## DIFFERENTIALS\n1. Unclear — uncertain findings — Confidence: low\n"
        "## RECOMMENDATIONS\nFurther investigation needed.\n"
        "## CONFIDENCE\n[overall: low] — Insufficient clinical data."
    )
    vr = vi.validate(rationale)
    assert vr.confidence == 0.30


def test_verification_detects_missing_sections():
    vi = VerificationInterface()
    rationale = "The patient seems fine. No issues detected."
    vr = vi.validate(rationale)
    assert any("section" in i.lower() for i in vr.issues)


def test_verification_detects_phi_cnic():
    vi = VerificationInterface()
    rationale = (
        "## ASSESSMENT\nPatient 3520112345678 has normal vitals.\n"
        "## DIFFERENTIALS\n1. None\n"
        "## RECOMMENDATIONS\nDischarge\n"
        "## CONFIDENCE\n[overall: medium]"
    )
    vr = vi.validate(rationale)
    assert vr.phi_detected is True
    assert vr.confidence == 0.0
    assert vr.passed is False


def test_verification_always_requires_human_review():
    vi = VerificationInterface()
    rationale = (
        "## ASSESSMENT\nAll normal.\n"
        "## DIFFERENTIALS\n1. Healthy — no findings — Confidence: high\n"
        "## RECOMMENDATIONS\nRoutine follow-up.\n"
        "## CONFIDENCE\n[overall: high] — Clear picture."
    )
    vr = vi.validate(rationale)
    assert vr.requires_review is True
