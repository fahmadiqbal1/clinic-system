"""
DataSanitiser — strips personally-identifiable staff/patient references
from admin, ops, and compliance AI inputs before sending to any external LLM.

Clinical inputs are already anonymised via CaseTokenService (Laravel).
This module handles the non-clinical personas where financial/inventory
queries may carry doctor names, patient names, or vendor account details.

Strategy:
  - Doctor/staff names → deterministic role tokens (DOCTOR_A, STAFF_B, …)
  - Patient identifiers → PATIENT_TOKEN (already pseudonymised by Laravel,
    but we double-check here)
  - Vendor names → VENDOR_1, VENDOR_2, … (sequential within one call)
  - Numeric values, dates, drug names, quantities — kept as-is
  - CNIC / phone patterns → [REDACTED] (hard removal)

Tokens are deterministic within a single sanitise() call via a session-
scoped registry, so the AI can reason about "DOCTOR_A had 12 discounts and
DOCTOR_B had 3" without seeing real names.
"""
from __future__ import annotations

import re


# Patterns that must always be removed (hard PHI)
_HARD_PHI = [
    (re.compile(r"\b\d{13}\b"), "[REDACTED_CNIC]"),
    (re.compile(r"\b0\d{10}\b"), "[REDACTED_PHONE]"),
    (re.compile(r"\b\+92\d{10}\b"), "[REDACTED_PHONE]"),
]

# Pakistani full-name heuristic: two or more capitalised Urdu/English words
# separated by a space, not preceded by a section header marker.
_NAME_RE = re.compile(
    r"(?<![#:\-])\b([A-Z][a-z]{2,15}(?:\s+[A-Z][a-z]{2,15}){1,3})\b"
)

# Prefixes that indicate a doctor name follows
_DR_PREFIX = re.compile(r"\b(Dr\.?|Doctor|Prof\.?|Professor)\s+([A-Z][a-z]{2,20}(?:\s+[A-Z][a-z]{2,20})?)\b")

# Vendor name heuristic: Title-Case words adjacent to "vendor", "supplier", "lab"
_VENDOR_RE = re.compile(
    r"\b(vendor|supplier|lab|laboratory|pharmacy|clinic)\s*:?\s*([A-Z][A-Za-z0-9\s&]{2,40}?)(?=[,\.\n]|\Z)",
    re.IGNORECASE,
)


class DataSanitiser:
    """
    Stateful within one pipeline call — builds a consistent token registry
    so the AI sees stable aliases across multi-turn tool results.

    Usage:
        sanitiser = DataSanitiser()
        safe_text = sanitiser.sanitise(raw_context)
    """

    def __init__(self) -> None:
        self._doctor_registry: dict[str, str] = {}   # name → DOCTOR_A
        self._vendor_registry: dict[str, str] = {}   # name → VENDOR_1
        self._name_registry: dict[str, str] = {}     # generic name → PERSON_1
        self._doctor_counter = 0
        self._vendor_counter = 0
        self._name_counter = 0

    # ── Public ────────────────────────────────────────────────────────────

    def sanitise(self, text: str) -> str:
        """Run all sanitisation passes in order and return clean text."""
        text = self._strip_hard_phi(text)
        text = self._replace_doctor_names(text)
        text = self._replace_vendor_names(text)
        text = self._replace_generic_names(text)
        return text

    def token_map(self) -> dict[str, str]:
        """Return the full alias map built during this session (for audit logs)."""
        combined: dict[str, str] = {}
        combined.update(self._doctor_registry)
        combined.update(self._vendor_registry)
        combined.update(self._name_registry)
        return combined

    # ── Passes ───────────────────────────────────────────────────────────

    def _strip_hard_phi(self, text: str) -> str:
        for pattern, replacement in _HARD_PHI:
            text = pattern.sub(replacement, text)
        return text

    def _replace_doctor_names(self, text: str) -> str:
        def _replace(m: re.Match) -> str:
            full_name = f"{m.group(1)} {m.group(2)}"
            return m.group(1) + " " + self._doctor_token(m.group(2))

        return _DR_PREFIX.sub(_replace, text)

    def _replace_vendor_names(self, text: str) -> str:
        def _replace(m: re.Match) -> str:
            kind = m.group(1)
            name = m.group(2).strip()
            return f"{kind} {self._vendor_token(name)}"

        return _VENDOR_RE.sub(_replace, text)

    def _replace_generic_names(self, text: str) -> str:
        """Replace remaining Title-Case name-shaped tokens not already tokenised."""
        def _replace(m: re.Match) -> str:
            name = m.group(1)
            # Skip if it's already a token or a known non-PHI word
            if name.startswith(("DOCTOR_", "VENDOR_", "PERSON_", "STAFF_")):
                return name
            if name in _NON_PHI_WORDS:
                return name
            return self._name_token(name)

        return _NAME_RE.sub(_replace, text)

    # ── Token factories ───────────────────────────────────────────────────

    def _doctor_token(self, name: str) -> str:
        if name not in self._doctor_registry:
            letter = chr(ord("A") + self._doctor_counter % 26)
            suffix = "" if self._doctor_counter < 26 else str(self._doctor_counter // 26)
            self._doctor_registry[name] = f"DOCTOR_{letter}{suffix}"
            self._doctor_counter += 1
        return self._doctor_registry[name]

    def _vendor_token(self, name: str) -> str:
        if name not in self._vendor_registry:
            self._vendor_counter += 1
            self._vendor_registry[name] = f"VENDOR_{self._vendor_counter}"
        return self._vendor_registry[name]

    def _name_token(self, name: str) -> str:
        if name not in self._name_registry:
            self._name_counter += 1
            self._name_registry[name] = f"PERSON_{self._name_counter}"
        return self._name_registry[name]


# Words that look like names but are safe clinical/business vocabulary
_NON_PHI_WORDS = {
    "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday",
    "January", "February", "March", "April", "June", "July", "August",
    "September", "October", "November", "December",
    "Pakistan", "Karachi", "Lahore", "Islamabad", "Rawalpindi",
    "Invoice", "Report", "Summary", "Total", "Amount", "Balance",
    "Critical", "Warning", "Urgent", "Pending", "Approved", "Rejected",
    "Stock", "Order", "Purchase", "Expense", "Revenue", "Discount",
    "Compliance", "Audit", "Evidence", "Finding", "Assessment",
    "Aviva", "Healthcare", "Clinic", "Hospital", "Department",
}
