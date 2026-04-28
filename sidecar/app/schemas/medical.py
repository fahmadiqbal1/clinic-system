"""
Immutable medical contracts — the anti-corruption layer between Laravel and AI backends.
Field constraints are load-bearing: changing them is a breaking change.

v2 additions (ETCSLV):
  - ConsultOutput.confidence: derived from VerificationInterface (no longer hardcoded)
  - ConsultOutput.retrieval_citations: real RAGFlow citations (no longer always [])
  - ConsultOutput.verification_issues: advisory gate failures
"""

from typing import Annotated, Literal, Optional
from pydantic import BaseModel, Field


# Constrained primitive types
CaseToken = Annotated[str, Field(pattern=r"^[a-f0-9]{64}$")]
AgeBand = Annotated[str, Field(pattern=r"^\d{1,3}-\d{1,3}$|^unknown$")]
ConfidenceLevel = Literal["low", "medium", "high"]


class VitalSet(BaseModel):
    bp_systolic: Optional[int] = Field(None, ge=40, le=260)
    bp_diastolic: Optional[int] = Field(None, ge=20, le=180)
    heart_rate: Optional[int] = Field(None, ge=20, le=250)
    temperature_c: Optional[float] = Field(None, ge=25.0, le=45.0)
    spo2: Optional[int] = Field(None, ge=0, le=100)
    chief_complaint: Optional[str] = None


class LabResult(BaseModel):
    test_name: str
    result: str
    unit: Optional[str] = None
    reference_range: Optional[str] = None


class LabResultSet(BaseModel):
    panel_name: str
    results: list[LabResult] = []


class RadiologyReport(BaseModel):
    imaging_type: str
    findings: Optional[str] = None
    image_count: int = 0


class ConsultInput(BaseModel):
    case_token: CaseToken
    age_band: AgeBand
    gender: str
    vitals: Optional[VitalSet] = None
    medications: list[str] = []
    lab_results: list[LabResultSet] = []
    radiology: list[RadiologyReport] = []
    custom_question: Optional[str] = None


class ConsultOutput(BaseModel):
    model_id: str
    prompt_hash: str
    rationale: str
    confidence: float = Field(ge=0.0, le=1.0)
    requires_human_review: bool
    retrieval_citations: list[str] = []
    verification_issues: list[str] = []  # advisory — non-empty means quality gates flagged


class RagQueryInput(BaseModel):
    query: str = Field(min_length=1, max_length=1000)
    collection: str = "general"


class RagQueryOutput(BaseModel):
    answer: str
    citations: list[str] = []
    model_id: str


class RagIngestInput(BaseModel):
    filePath: Optional[str] = None   # path on the sidecar filesystem (for PDF uploads)
    content: Optional[str] = None    # raw text to ingest directly (for DB corpus sync)
    collection: str = "general"


class RagIngestOutput(BaseModel):
    ingestion_id: str
    status: str


class ForecastInput(BaseModel):
    days_ahead: int = Field(default=30, ge=1, le=365)


class ForecastOutput(BaseModel):
    forecast: list[dict]
    model_id: str
    generated_at: str
