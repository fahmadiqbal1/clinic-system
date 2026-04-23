import pytest
from pydantic import ValidationError

from app.schemas.medical import ConsultInput, VitalSet


def test_vital_set_rejects_bp_systolic_above_max():
    with pytest.raises(ValidationError):
        VitalSet(bp_systolic=300, bp_diastolic=80)


def test_vital_set_rejects_bp_diastolic_below_min():
    with pytest.raises(ValidationError):
        VitalSet(bp_systolic=120, bp_diastolic=10)


def test_vital_set_accepts_valid_readings():
    v = VitalSet(bp_systolic=120, bp_diastolic=80, heart_rate=72, temperature_c=36.6, spo2=98)
    assert v.bp_systolic == 120
    assert v.spo2 == 98


def test_consult_input_accepts_64_hex_case_token():
    ci = ConsultInput(case_token="a" * 64, age_band="30-34", gender="male")
    assert ci.case_token == "a" * 64


def test_consult_input_rejects_short_case_token():
    with pytest.raises(ValidationError):
        ConsultInput(case_token="abc", age_band="30-34", gender="male")


def test_consult_input_rejects_non_hex_case_token():
    with pytest.raises(ValidationError):
        ConsultInput(case_token="g" * 64, age_band="30-34", gender="male")


def test_age_band_accepts_unknown():
    ci = ConsultInput(case_token="a" * 64, age_band="unknown", gender="female")
    assert ci.age_band == "unknown"
