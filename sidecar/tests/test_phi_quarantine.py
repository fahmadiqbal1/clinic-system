"""
Tests for PHI detection, redaction, and quarantine in verification_interface.py.
No real model calls — tests operate on the VerificationInterface directly.
"""
from __future__ import annotations

import pytest

from app.agent.verification_interface import VerificationInterface


@pytest.fixture()
def vi():
    # Use minimal configuration so length/section gates don't mask PHI gate
    return VerificationInterface(required_sections=[], min_length=0)


class TestPhiRedaction:
    """PHI patterns are removed from the rationale before it reaches the caller."""

    def test_cnic_is_redacted(self, vi):
        # PHI regex: \b\d{13}\b — 13 consecutive digits
        raw = "Patient CNIC: 3520112345671 needs follow-up."
        result = vi.validate(raw)
        assert "3520112345671" not in (result.redacted_rationale or "")
        assert "[REDACTED_CNIC]" in (result.redacted_rationale or "")

    def test_phone_is_redacted(self, vi):
        # PHI regex: \b0\d{10}\b — 0 followed by 10 digits (11 total)
        raw = "Call patient at 03001234567 tomorrow."
        result = vi.validate(raw)
        assert "03001234567" not in (result.redacted_rationale or "")
        assert "[REDACTED_PHONE]" in (result.redacted_rationale or "")

    def test_phi_in_output_sets_confidence_zero(self, vi):
        raw = "CNIC 3520112345671 belongs to the patient."
        result = vi.validate(raw)
        assert result.confidence == 0.0

    def test_phi_output_is_quarantined(self, vi):
        raw = "Contact 03219876543 for details."
        result = vi.validate(raw)
        assert result.passed is False

    def test_clean_output_passes_through(self, vi):
        raw = (
            "## ASSESSMENT\nPatient presents with mild fever.\n"
            "## RECOMMENDATION\nRest and hydration.\n"
            "## CONFIDENCE\n0.85"
        )
        result = vi.validate(raw)
        # No PHI → phi_detected must be False regardless of other gates
        assert result.phi_detected is False

    def test_no_phi_redacted_rationale_matches_original(self, vi):
        raw = "Normal ECG trace observed."
        result = vi.validate(raw)
        assert result.redacted_rationale == raw or result.redacted_rationale is None

    def test_multiple_phi_patterns_all_redacted(self, vi):
        # 13-digit CNIC + 11-digit phone (0 prefix)
        raw = "CNIC 3520112345671, phone 03331112223 recorded."
        result = vi.validate(raw)
        redacted = result.redacted_rationale or ""
        assert "3520112345671" not in redacted
        assert "03331112223" not in redacted
        assert result.confidence == 0.0


class TestDataSanitiser:
    """DataSanitiser replaces doctor names and vendor names with tokens."""

    def test_doctor_name_replaced(self):
        from app.services.data_sanitiser import DataSanitiser
        ds = DataSanitiser()
        out = ds.sanitise("Dr. Ahmed reviewed the case.")
        assert "Ahmed" not in out
        assert "DOCTOR_" in out

    def test_vendor_name_replaced(self):
        from app.services.data_sanitiser import DataSanitiser
        ds = DataSanitiser()
        # Vendor regex fires when a context word ("supplier", "vendor", etc.) precedes the name
        out = ds.sanitise("supplier: Global Pharma Ltd placed the order.")
        assert "Global Pharma Ltd" not in out
        assert "VENDOR_" in out or "PERSON_" in out  # either token prefix is correct

    def test_idempotent_on_clean_text(self):
        from app.services.data_sanitiser import DataSanitiser
        ds = DataSanitiser()
        text = "Paracetamol 500mg stock is low."
        out = ds.sanitise(text)
        assert "Paracetamol" in out

    def test_token_map_has_replacements(self):
        from app.services.data_sanitiser import DataSanitiser
        ds = DataSanitiser()
        ds.sanitise("Dr. Khan visited Novartis warehouse.")
        tmap = ds.token_map()
        assert len(tmap) > 0

    def test_stopwords_not_replaced(self):
        from app.services.data_sanitiser import DataSanitiser
        ds = DataSanitiser()
        text = "Blood pressure is normal, no clinical concerns."
        out = ds.sanitise(text)
        assert "Blood" in out or "blood" in out.lower()
