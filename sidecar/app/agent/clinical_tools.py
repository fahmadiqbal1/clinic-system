"""
Clinical tool factories — the T pillar's clinical persona.

Tools:
  - rag_query           : RAGFlow guidelines retrieval (existing in harness.py)
  - vital_alert         : pure-Python critical-vital detection (fail-closed)
  - medication_safety   : RAGFlow lookup for med + 'contraindication' (fail-open)

Each factory takes the ConsultInput body and returns a Tool ready for
ToolRegistry.register(). Factories produce per-request closures so the
tool's invoke_fn already has the bound query / vitals.
"""
from __future__ import annotations

import logging
from typing import Any

from app.agent.tool_registry import Tool
from app.services import ragflow

logger = logging.getLogger(__name__)


# ── Critical vital ranges (life-threatening — quoted thresholds from
#    standard EWS / NEWS2 escalation criteria) ───────────────────────────────
_VITAL_RULES: list[tuple[str, Any]] = [
    # field, predicate(value) → (label, severity)
]


def _check_vitals(vitals: Any) -> list[dict]:
    """Return a list of critical findings from a VitalSet (or None)."""
    findings: list[dict] = []
    if vitals is None:
        return findings

    bp_s = getattr(vitals, "bp_systolic", None)
    bp_d = getattr(vitals, "bp_diastolic", None)
    if bp_s is not None:
        if bp_s >= 180 or (bp_d is not None and bp_d >= 120):
            findings.append({
                "label": "Hypertensive crisis",
                "value": f"BP {bp_s}/{bp_d}",
                "severity": "critical",
            })
        elif bp_s <= 90:
            findings.append({
                "label": "Hypotension",
                "value": f"BP {bp_s}/{bp_d}",
                "severity": "critical",
            })

    hr = getattr(vitals, "heart_rate", None)
    if hr is not None:
        if hr >= 150:
            findings.append({"label": "Severe tachycardia", "value": f"HR {hr}", "severity": "critical"})
        elif hr <= 40:
            findings.append({"label": "Severe bradycardia", "value": f"HR {hr}", "severity": "critical"})

    spo2 = getattr(vitals, "spo2", None)
    if spo2 is not None and spo2 < 88:
        findings.append({"label": "Severe hypoxaemia", "value": f"SpO2 {spo2}%", "severity": "critical"})

    temp = getattr(vitals, "temperature_c", None)
    if temp is not None:
        if temp >= 40.0:
            findings.append({"label": "Hyperpyrexia", "value": f"{temp}°C", "severity": "critical"})
        elif temp <= 35.0:
            findings.append({"label": "Hypothermia", "value": f"{temp}°C", "severity": "critical"})

    return findings


def make_vital_alert_tool(body: Any) -> Tool:
    """
    Pure-Python critical-vital detection.
    Fail-closed: any unexpected exception raises (the harness will then
    treat the tool result as missing — but that's a programmer bug, not
    a runtime input error, so we don't want to silently swallow it).
    """

    async def _invoke(_ctx: dict) -> dict:
        findings = _check_vitals(getattr(body, "vitals", None))
        if not findings:
            return {
                "tool": "vital_alert",
                "answer": "Vital signs within non-critical bounds.",
                "findings": [],
            }
        bullet = "\n".join(
            f"- {f['severity'].upper()}: {f['label']} ({f['value']})" for f in findings
        )
        return {
            "tool": "vital_alert",
            "answer": f"⚠️ CRITICAL VITAL FINDINGS:\n{bullet}",
            "findings": findings,
        }

    return Tool(
        name="vital_alert",
        description="Detects life-threatening vital sign values from the consultation input.",
        schema={"type": "object", "properties": {}, "required": []},
        invoke_fn=_invoke,
        fail_open=False,
    )


def make_medication_safety_tool(body: Any) -> Tool:
    """
    RAGFlow lookup for each medication + 'contraindication'.
    Fail-open: degrades to empty findings if RAGFlow is unconfigured/down.
    """
    meds: list[str] = list(getattr(body, "medications", []) or [])[:5]

    async def _invoke(_ctx: dict) -> dict:
        if not meds:
            return {
                "tool": "medication_safety",
                "answer": "",
                "citations": [],
            }

        # Single composite query is cheaper than N round-trips and still
        # gives the model useful guidance to cross-check.
        query_text = (
            "Contraindications and major drug interactions for: "
            + ", ".join(meds)
        )
        try:
            result = await ragflow.query(query_text, collection="general")
        except Exception as exc:
            logger.debug("medication_safety: RAGFlow query failed (%s)", exc)
            return {
                "tool": "medication_safety",
                "answer": "",
                "citations": [],
            }
        return {
            "tool": "medication_safety",
            "answer": result.get("answer", ""),
            "citations": result.get("citations", []),
        }

    return Tool(
        name="medication_safety",
        description="Retrieves contraindication / interaction guidance for the patient's medications.",
        schema={"type": "object", "properties": {}, "required": []},
        invoke_fn=_invoke,
        fail_open=True,
    )


def make_rag_query_tool(body: Any) -> Tool:
    """
    Promote the inline rag_query factory from harness.py to this module so
    every clinical tool lives in one place. Same behaviour: fail-open RAGFlow
    query keyed off the chief complaint.
    """
    query_text = (
        body.vitals.chief_complaint
        if getattr(body, "vitals", None) and getattr(body.vitals, "chief_complaint", None)
        else "clinical assessment guidelines"
    )

    async def _invoke(_ctx: dict) -> dict:
        try:
            result = await ragflow.query(query_text, collection="general")
        except Exception as exc:
            logger.debug("rag_query: RAGFlow query failed (%s)", exc)
            return {"tool": "rag_query", "answer": "", "citations": []}
        return {
            "tool": "rag_query",
            "answer": result.get("answer", ""),
            "citations": result.get("citations", []),
        }

    return Tool(
        name="rag_query",
        description="Retrieve relevant clinical guidelines from the RAGFlow knowledge base.",
        schema={
            "type": "object",
            "properties": {"query": {"type": "string"}},
        },
        invoke_fn=_invoke,
        fail_open=True,
    )
