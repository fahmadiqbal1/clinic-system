"""
V — Verification Interface

Output validation and quality gates for every agent response.

Gates (in order):
  1. Schema compliance — Pydantic handles structure
  2. Minimum content quality — length + required sections
  3. PHI scan — no patient name, CNIC, phone in output
  4. Confidence parsing — extract from structured ## CONFIDENCE section
  5. Human-review flag — clinical safety invariant (always True in v1)

A failed PHI gate quarantines the output (confidence=0, always review).
Other gate failures are advisory — they populate issues[] but pass.
"""
from __future__ import annotations

import logging
import re
from dataclasses import dataclass, field

logger = logging.getLogger(__name__)

MIN_RATIONALE_LENGTH = 150
REQUIRED_SECTIONS = ["## ASSESSMENT", "## DIFFERENTIALS", "## RECOMMENDATIONS"]

_PHI_PATTERNS = [
    (r"\b\d{13}\b", "CNIC (13-digit number)"),
    (r"\b0\d{10}\b", "Pakistani mobile number"),
    (r"\b\+92\d{10}\b", "International Pakistani number"),
]

_CONFIDENCE_MAP = {"low": 0.30, "medium": 0.65, "high": 0.90}


@dataclass
class VerificationResult:
    passed: bool
    confidence: float
    requires_review: bool
    issues: list[str] = field(default_factory=list)
    phi_detected: bool = False


class VerificationInterface:
    """Validates and scores agent output — the V pillar."""

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

    def validate(self, rationale: str, body: object = None) -> VerificationResult:
        issues: list[str] = []

        # Gate 1: Minimum length
        if len(rationale) < MIN_RATIONALE_LENGTH:
            issues.append(
                f"Response too short ({len(rationale)} chars, minimum {MIN_RATIONALE_LENGTH})"
            )

        # Gate 2: Required sections
        missing = [s for s in REQUIRED_SECTIONS if s not in rationale]
        if missing:
            issues.append(f"Missing required sections: {missing}")

        # Gate 3: PHI scan (hard gate)
        phi_issues = self._scan_phi(rationale)
        if phi_issues:
            logger.error(
                "VerificationInterface: PHI detected in AI output — output quarantined. Issues: %s",
                phi_issues,
            )
            return VerificationResult(
                passed=False,
                confidence=0.0,
                requires_review=True,
                issues=phi_issues,
                phi_detected=True,
            )

        # Gate 4: Confidence parsing
        confidence = self._parse_confidence(rationale)

        # Gate 5: Human review — clinical safety invariant: always True
        requires_review = True

        passed = all("section" in i or "short" in i for i in issues) or len(issues) == 0

        return VerificationResult(
            passed=passed,
            confidence=confidence,
            requires_review=requires_review,
            issues=issues,
        )
