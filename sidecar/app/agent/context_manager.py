"""
C — Context Manager

Token-budgeted context assembly that prevents context ROT.

Strategies:
  - Enforces a hard token budget (MedGemma 8192 ctx; leaves 2048 for output)
  - Prioritises high-signal data: vitals > labs > meds > radiology > history
  - Caps collections at safe limits before token counting
  - Injects prior-consultation summary at the top (recency bias)
  - Builds a strict system prompt that governs model behaviour
"""
from __future__ import annotations

import logging
from dataclasses import dataclass, field

logger = logging.getLogger(__name__)

# Conservative budget: 6000 prompt tokens → 2048+ for output
TOKEN_BUDGET = 6000
CHARS_PER_TOKEN = 3.5  # medical English estimate


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


class ContextManager:
    """Assembles a token-budgeted AgentContext — the C pillar."""

    # ------------------------------------------------------------------ #
    # System prompt — governs model behaviour for every consultation       #
    # ------------------------------------------------------------------ #
    SYSTEM_PROMPT = (
        "You are MedGemma, a clinical AI assistant providing second opinions to human doctors. "
        "Strict rules:\n"
        "1. Never reference personal identifiers — only case tokens.\n"
        "2. Always recommend human clinical review — this is non-negotiable.\n"
        "3. Think step by step before concluding. Consider all provided data.\n"
        "4. Structure every response with EXACTLY these markdown sections:\n"
        "   ## ASSESSMENT\n"
        "   (overall clinical picture)\n"
        "   ## DIFFERENTIALS\n"
        "   1. [Diagnosis] — [Supporting evidence] — [Confidence: low|medium|high]\n"
        "   ## RECOMMENDATIONS\n"
        "   (investigations, management, urgency)\n"
        "   ## CONFIDENCE\n"
        "   [overall: low|medium|high] — [one sentence rationale]\n"
        "5. If any vital sign is life-threatening, open ASSESSMENT with: ⚠️ URGENT:"
    )

    def _tokens(self, text: str) -> int:
        return max(1, int(len(text) / CHARS_PER_TOKEN))

    def build(self, body: object, prior_context: dict | None = None) -> AgentContext:
        """
        Assemble a token-budgeted context from ConsultInput and optional prior state.
        Truncates lower-priority data when the budget is exceeded.
        """
        budget = TOKEN_BUDGET - self._tokens(self.SYSTEM_PROMPT)
        lines: list[str] = []

        # ── Prior context (highest priority — prevents context ROT) ──────
        if prior_context and prior_context.get("last_summary"):
            prior = f"[Prior consultation summary]\n{prior_context['last_summary']}"
            cost = self._tokens(prior)
            if cost < budget * 0.15:  # cap at 15% of budget
                lines.append(prior)
                budget -= cost

        # ── Demographics (always include) ─────────────────────────────────
        demo = f"Case Token: {body.case_token} | Gender: {body.gender} | Age Band: {body.age_band}"
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
            "Think step by step. Provide a comprehensive clinical second opinion "
            "using ALL data above. Use the required section structure."
        )
        lines.append(instruction)

        user_content = "\n".join(lines)
        budget_used = TOKEN_BUDGET - budget

        if budget_used > TOKEN_BUDGET * 0.9:
            logger.warning("ContextManager: high budget utilisation %d/%d tokens", budget_used, TOKEN_BUDGET)

        return AgentContext(
            system_prompt=self.SYSTEM_PROMPT,
            messages=[
                {"role": "system", "content": self.SYSTEM_PROMPT},
                {"role": "user", "content": user_content},
            ],
            budget_used=budget_used,
            budget_total=TOKEN_BUDGET,
        )
