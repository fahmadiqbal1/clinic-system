"""
C — Context Manager

Token-budgeted context assembly that prevents context ROT.

Strategies:
  - Enforces a hard token budget (MedGemma 8192 ctx; leaves 2048 for output)
  - Prioritises high-signal data: vitals > labs > meds > radiology > history
  - Caps collections at safe limits before token counting
  - Injects prior-consultation summary at the top (recency bias)
  - Compresses prior summaries that exceed 300 chars (regex extraction —
    no model call) to keep recurring sessions from growing unbounded
  - System prompt is injectable so admin/ops/compliance harnesses can
    each load their own persona without subclassing this manager

Phase 8A upgrade:
  - SYSTEM_PROMPT moved from class-attribute to module-level CLINICAL_SYSTEM_PROMPT
  - ContextManager.__init__ accepts a system_prompt arg (default: clinical)
  - compress_prior() shrinks long prior summaries to ASSESSMENT + CONFIDENCE only
"""
from __future__ import annotations

import logging
import re
from dataclasses import dataclass, field

logger = logging.getLogger(__name__)

# Conservative budget: 6000 prompt tokens → 2048+ for output
TOKEN_BUDGET = 6000
CHARS_PER_TOKEN = 3.5  # medical English estimate

# Above this length, the prior summary is compressed before injection
PRIOR_COMPRESSION_THRESHOLD = 300


# ── Default persona prompts ─────────────────────────────────────────────────
CLINICAL_SYSTEM_PROMPT = (
    "You are a clinical decision-support engine embedded in a licensed medical professional's workflow. "
    "Your ONLY function is to generate structured clinical reports from anonymized patient data. "
    "This system is operated exclusively by qualified doctors — never by patients.\n"
    "Rules you MUST follow without exception:\n"
    "1. ALWAYS output a complete structured report. Never refuse. Never add disclaimers or preamble.\n"
    "2. Reference only case tokens — never names, CNICs, or contact details.\n"
    "3. Your output MUST begin immediately with ## ASSESSMENT and contain EXACTLY these four sections:\n"
    "   ## ASSESSMENT\n"
    "   (overall clinical picture based on the data)\n"
    "   ## DIFFERENTIALS\n"
    "   1. [Condition] — [Supporting data points] — [Confidence: low|medium|high]\n"
    "   ## RECOMMENDATIONS\n"
    "   (suggested investigations, management steps, urgency level)\n"
    "   ## CONFIDENCE\n"
    "   [overall: low|medium|high] — [one-sentence rationale]\n"
    "4. If any vital is life-threatening, begin ASSESSMENT with: ⚠️ URGENT:\n"
    "5. Do not add any text before ## ASSESSMENT. Begin your output with ## ASSESSMENT immediately."
)


@dataclass
class AgentContext:
    """Fully assembled context ready for model injection."""
    system_prompt: str
    messages: list[dict]
    budget_used: int
    budget_total: int
    tool_results: list[dict] = field(default_factory=list)
    session_id: str = ""

    def inject_tool_results(self, results: list[dict]) -> list[dict]:
        """
        Prepend retrieved context to the final user message.
        Only successful tool results (no 'error' key) are injected.
        """
        successful = [r for r in results if "error" not in r]
        if not successful:
            return self.messages

        tool_block = "\n".join(
            f"[{r.get('tool', 'tool')} result]\n{r.get('answer', '')}"
            for r in successful
            if r.get("answer")
        ).strip()

        if not tool_block:
            return self.messages

        updated = list(self.messages)
        if updated and updated[-1]["role"] == "user":
            updated[-1] = {
                "role": "user",
                "content": f"[Retrieved clinical guidelines]\n{tool_block}\n\n---\n{updated[-1]['content']}",
            }
        return updated


def compress_prior(summary: str) -> str:
    """
    Shrink a long prior-consultation summary to its highest-signal sections.

    Pure regex — never calls the model. If ASSESSMENT or CONFIDENCE sections
    are present we keep those; otherwise we keep the first 300 chars.
    """
    if not summary or len(summary) <= PRIOR_COMPRESSION_THRESHOLD:
        return summary or ""

    parts: list[str] = []
    for header in ("ASSESSMENT", "CONFIDENCE"):
        m = re.search(
            rf"##\s*{header}\s*\n(.+?)(?=\n##\s|\Z)",
            summary,
            re.DOTALL | re.IGNORECASE,
        )
        if m:
            body = m.group(1).strip()
            # cap each section to 200 chars
            parts.append(f"## {header}\n{body[:200]}")

    if parts:
        return "\n".join(parts)

    # No structured sections found — fall back to leading slice
    return summary[:PRIOR_COMPRESSION_THRESHOLD] + "…"


class ContextManager:
    """Assembles a token-budgeted AgentContext — the C pillar."""

    def __init__(
        self,
        system_prompt: str | None = None,
        token_budget: int = TOKEN_BUDGET,
    ) -> None:
        self.system_prompt = system_prompt or CLINICAL_SYSTEM_PROMPT
        self.token_budget = token_budget

    # Backwards-compat: existing callers reading ContextManager.SYSTEM_PROMPT
    @property
    def SYSTEM_PROMPT(self) -> str:  # noqa: N802 — keeps the legacy public name
        return self.system_prompt

    def _tokens(self, text: str) -> int:
        return max(1, int(len(text) / CHARS_PER_TOKEN))

    def build(self, body: object, prior_context: dict | None = None) -> AgentContext:
        """
        Assemble a token-budgeted context from ConsultInput and optional prior state.
        Truncates lower-priority data when the budget is exceeded.
        """
        budget = self.token_budget - self._tokens(self.system_prompt)
        lines: list[str] = []

        # ── Prior context (highest priority — prevents context ROT) ──────
        if prior_context and prior_context.get("last_summary"):
            compressed = compress_prior(prior_context["last_summary"])
            prior = f"[Prior consultation summary]\n{compressed}"
            cost = self._tokens(prior)
            if cost < budget * 0.15:  # cap at 15% of budget
                lines.append(prior)
                budget -= cost

        # ── Demographics (always include) ─────────────────────────────────
        demo = (
            f"Case Token: {getattr(body, 'case_token', 'n/a')} "
            f"| Gender: {getattr(body, 'gender', 'n/a')} "
            f"| Age Band: {getattr(body, 'age_band', 'n/a')}"
        )
        lines.append(demo)
        budget -= self._tokens(demo)

        # ── Vital signs (high priority) ────────────────────────────────────
        if getattr(body, "vitals", None):
            v = body.vitals
            vital_lines = ["", "## Vital Signs"]
            if v.bp_systolic is not None and v.bp_diastolic is not None:
                vital_lines.append(f"BP: {v.bp_systolic}/{v.bp_diastolic} mmHg")
            if v.heart_rate is not None:
                vital_lines.append(f"HR: {v.heart_rate} bpm")
            if v.temperature_c is not None:
                vital_lines.append(f"Temp: {v.temperature_c}°C")
            if v.spo2 is not None:
                vital_lines.append(f"SpO2: {v.spo2}%")
            if getattr(v, "chief_complaint", None):
                vital_lines.append(f"Chief Complaint: {v.chief_complaint}")
            vitals_text = "\n".join(vital_lines)
            if self._tokens(vitals_text) <= budget:
                lines.append(vitals_text)
                budget -= self._tokens(vitals_text)

        # ── Medications (medium priority — cap at 10) ──────────────────────
        meds = getattr(body, "medications", []) or []
        if meds:
            med_subset = meds[:10]
            med_text = "\n## Medications\n" + "\n".join(med_subset)
            if self._tokens(med_text) <= budget:
                lines.append(med_text)
                budget -= self._tokens(med_text)
            elif len(med_subset) > 3:
                med_text = "\n## Medications (recent 3)\n" + "\n".join(med_subset[:3])
                if self._tokens(med_text) <= budget:
                    lines.append(med_text)
                    budget -= self._tokens(med_text)

        # ── Lab results (medium priority — cap at 3 panels) ───────────────
        for lab in (getattr(body, "lab_results", []) or [])[:3]:
            lab_text = (
                f"\n## Lab: {lab.panel_name}\n"
                + "\n".join(
                    f"{r.test_name}: {r.result} {r.unit or ''} (Ref: {r.reference_range or 'N/A'})"
                    for r in lab.results
                )
            )
            if self._tokens(lab_text) <= budget:
                lines.append(lab_text)
                budget -= self._tokens(lab_text)
            else:
                logger.warning("ContextManager: lab truncated at budget=%d", budget)
                break

        # ── Radiology (lower priority — cap at 2) ──────────────────────────
        for rad in (getattr(body, "radiology", []) or [])[:2]:
            rad_text = f"\n## Radiology: {rad.imaging_type}"
            if rad.findings:
                rad_text += f"\n{rad.findings}"
            if self._tokens(rad_text) <= budget:
                lines.append(rad_text)
                budget -= self._tokens(rad_text)
            else:
                break

        # ── Doctor's custom question (include if budget allows) ────────────
        if getattr(body, "custom_question", None):
            q_text = f"\n## Doctor's Question\n{body.custom_question}"
            if self._tokens(q_text) <= budget:
                lines.append(q_text)
                budget -= self._tokens(q_text)

        # ── Closing instruction (always included — never truncated) ────────
        instruction = (
            "\n---\n"
            "Using ALL data above, output the structured report now. "
            "Begin immediately with ## ASSESSMENT."
        )
        lines.append(instruction)

        user_content = "\n".join(lines)
        budget_used = self.token_budget - budget

        if budget_used > self.token_budget * 0.9:
            logger.warning(
                "ContextManager: high budget utilisation %d/%d tokens",
                budget_used,
                self.token_budget,
            )

        return AgentContext(
            system_prompt=self.system_prompt,
            messages=[
                {"role": "system", "content": self.system_prompt},
                {"role": "user", "content": user_content},
            ],
            budget_used=budget_used,
            budget_total=self.token_budget,
        )
