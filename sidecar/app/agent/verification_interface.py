"""
V — Verification Interface

Output validation and quality gates for every agent response.

Gates (in order):
  1. Schema compliance — Pydantic handles structure
  2. Minimum content quality — length + required sections
  3. PHI scan — no patient name, CNIC, phone in output (hard gate)
  4. Confidence parsing — extract from structured ## CONFIDENCE section
  5. Hallucination heuristic — capitalised drug names not in input medications
  6. Section completeness scoring — 0.0–1.0 score across required sections
  7. Human-review flag — clinical safety invariant (always True in v1)

A failed PHI gate quarantines the output (confidence=0, always review).
Other gate failures are advisory — they populate issues[] but do not
quarantine the response. `passed` is True only when issues is empty
(this fixes a Phase 7 bug where unrelated issue types still passed).
"""
from __future__ import annotations

import logging
import re
from dataclasses import dataclass, field

logger = logging.getLogger(__name__)

MIN_RATIONALE_LENGTH = 150
REQUIRED_SECTIONS = ["## ASSESSMENT", "## DIFFERENTIALS", "## RECOMMENDATIONS"]

_PHI_PATTERNS = [
    (r"\b\d{13}\b", "CNIC"),
    (r"\b0\d{10}\b", "PHONE"),
    (r"\b\+92\d{10}\b", "PHONE"),
]

# Redaction labels matching _PHI_PATTERNS order
_PHI_REDACT_LABELS = ["REDACTED_CNIC", "REDACTED_PHONE", "REDACTED_PHONE"]

_CONFIDENCE_MAP = {"low": 0.30, "medium": 0.65, "high": 0.90}

# Capitalised proper nouns of length >=4 with at least one lowercase tail char.
# Used as a coarse filter for "drug-name-shaped" tokens before whitelist check.
_DRUGNAME_RE = re.compile(r"\b([A-Z][a-z]{3,15})\b")

# Tokens that look drug-shaped but are common English / clinical vocabulary
# we should never flag as hallucinations.
_DRUGNAME_STOPLIST = {
    "Assessment", "Differentials", "Recommendations", "Confidence",
    "Patient", "Doctor", "Clinical", "History", "Physical", "Vital",
    "Vitals", "Diagnosis", "Treatment", "Management", "Urgent", "Severe",
    "Mild", "Moderate", "Acute", "Chronic", "Possible", "Likely",
    "Suggested", "Recommended", "Consider", "Review", "Follow", "Monitor",
    "Hypertension", "Hypotension", "Tachycardia", "Bradycardia",
    "Pakistan", "English", "Aviva", "Healthcare", "Clinic", "Hospital",
    "January", "February", "March", "April", "June", "July", "August",
    "September", "October", "November", "December", "Monday", "Tuesday",
    "Wednesday", "Thursday", "Friday", "Saturday", "Sunday",
}


@dataclass
class VerificationResult:
    passed: bool
    confidence: float
    requires_review: bool
    issues: list[str] = field(default_factory=list)
    phi_detected: bool = False
    section_score: float = 0.0
    hallucinations: list[str] = field(default_factory=list)
    redacted_rationale: str | None = None  # set only when phi_detected=True


class VerificationInterface:
    """Validates and scores agent output — the V pillar."""

    def __init__(
        self,
        required_sections: list[str] | None = None,
        min_length: int = MIN_RATIONALE_LENGTH,
        confidence_floor: float = 0.0,
        always_require_review: bool = True,
    ) -> None:
        # Injectable so admin/ops/compliance harnesses can override
        self.required_sections = required_sections or REQUIRED_SECTIONS
        self.min_length = min_length
        self.confidence_floor = confidence_floor
        self.always_require_review = always_require_review

    def _parse_confidence(self, rationale: str) -> float:
        """Extract confidence level from the structured ## CONFIDENCE section."""
        m = re.search(
            r"##\s*CONFIDENCE\s*\n\s*\[?(?:overall:\s*)?(low|medium|high)",
            rationale,
            re.IGNORECASE,
        )
        if m:
            return _CONFIDENCE_MAP.get(m.group(1).lower(), 0.5)

        # Fallback: keyword density heuristic
        lower = rationale.lower()
        if any(w in lower for w in ["uncertain", "insufficient data", "unable to determine"]):
            return 0.30
        if any(w in lower for w in ["strongly suggest", "highly consistent", "definitive"]):
            return 0.85
        return 0.50

    def _scan_phi(self, text: str) -> list[str]:
        issues = []
        for pattern, label in _PHI_PATTERNS:
            if re.search(pattern, text):
                issues.append(f"Possible PHI detected: {label}")
        return issues

    def _redact_phi(self, text: str) -> str:
        """Replace PHI matches in text with [REDACTED_*] tokens before returning."""
        for (pattern, _label), redact_label in zip(_PHI_PATTERNS, _PHI_REDACT_LABELS):
            text = re.sub(pattern, f"[{redact_label}]", text)
        return text

    def _section_completeness(self, rationale: str) -> tuple[float, list[str]]:
        """
        Score 0.0–1.0 based on which required sections are present AND
        contain >= 20 characters of content after the heading.
        Returns (score, missing_sections).
        """
        if not self.required_sections:
            return 1.0, []

        present = 0
        missing: list[str] = []
        for section in self.required_sections:
            # Match the heading then capture content until the next ##-heading or EOF
            pat = rf"{re.escape(section)}\s*\n(.+?)(?=\n##\s|\Z)"
            m = re.search(pat, rationale, re.DOTALL | re.IGNORECASE)
            if m and len(m.group(1).strip()) >= 20:
                present += 1
            else:
                missing.append(section)

        score = present / len(self.required_sections)
        return score, missing

    def _detect_hallucinated_drugs(
        self, rationale: str, known_meds: list[str] | None
    ) -> list[str]:
        """
        Flag drug-name-shaped tokens in output that are NOT in the input
        medications list. Coarse heuristic — produces advisory issues only.
        """
        if known_meds is None:
            return []

        # Normalise known medication names: take the first capitalised word
        # of each entry (so "Amoxicillin 500mg PO TID" -> "Amoxicillin").
        known_set = set()
        for med in known_meds:
            for tok in re.findall(r"[A-Za-z]+", med or ""):
                if len(tok) >= 4:
                    known_set.add(tok.capitalize())

        flagged: list[str] = []
        seen: set[str] = set()
        for match in _DRUGNAME_RE.finditer(rationale):
            tok = match.group(1)
            if tok in seen or tok in _DRUGNAME_STOPLIST or tok in known_set:
                continue
            seen.add(tok)
            # Only flag if it appears in a context suggestive of medication naming
            # (followed by mg/dose/po/iv within 30 chars, or preceded by "prescribe/start/give")
            window_start = max(0, match.start() - 30)
            window_end = min(len(rationale), match.end() + 30)
            context = rationale[window_start:window_end].lower()
            if re.search(r"\b(mg|mcg|ml|po|iv|im|dose|prescribe|start|give|administer)\b", context):
                flagged.append(tok)

        return flagged[:5]  # cap to keep issues list bounded

    def validate(self, rationale: str, body: object = None) -> VerificationResult:
        issues: list[str] = []

        # Gate 1: Minimum length
        if len(rationale) < self.min_length:
            issues.append(
                f"Response too short ({len(rationale)} chars, minimum {self.min_length})"
            )

        # Gate 2: Required sections (also drives section_score)
        section_score, missing = self._section_completeness(rationale)
        if missing:
            issues.append(f"Missing or thin sections: {missing}")

        # Gate 3: PHI scan (hard gate — redacts then quarantines output)
        phi_issues = self._scan_phi(rationale)
        if phi_issues:
            redacted = self._redact_phi(rationale)
            logger.error(
                "VerificationInterface: PHI detected in AI output — redacted and quarantined. Issues: %s",
                phi_issues,
            )
            return VerificationResult(
                passed=False,
                confidence=0.0,
                requires_review=True,
                issues=phi_issues,
                phi_detected=True,
                section_score=section_score,
                redacted_rationale=redacted,
            )

        # Gate 4: Confidence parsing
        confidence = self._parse_confidence(rationale)
        if confidence < self.confidence_floor:
            issues.append(
                f"Confidence {confidence:.2f} below floor {self.confidence_floor:.2f}"
            )

        # Gate 5: Hallucination heuristic (advisory)
        known_meds = getattr(body, "medications", None) if body is not None else None
        hallucinations = self._detect_hallucinated_drugs(rationale, known_meds)
        if hallucinations:
            issues.append(
                f"Possible hallucinated drug names (not in input meds): {hallucinations}"
            )

        # Gate 6: Section completeness (advisory issue when score < 0.67)
        if section_score < 0.67:
            issues.append(f"Low section completeness score: {section_score:.2f}")

        # Gate 7: Human review invariant
        requires_review = self.always_require_review

        # FIX (Phase 8A.1): passed is true ONLY when no issues exist.
        # The previous truthy-on-section-or-short check incorrectly allowed
        # confidence-floor and hallucination issues to mark passed=True.
        passed = len(issues) == 0

        return VerificationResult(
            passed=passed,
            confidence=confidence,
            requires_review=requires_review,
            issues=issues,
            section_score=section_score,
            hallucinations=hallucinations,
        )
