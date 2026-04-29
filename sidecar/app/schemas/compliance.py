"""
Compliance AI — request/response schemas.

The compliance persona verifies the audit chain, scans for PHI access
anomalies, and reports SOC2 evidence gaps. Output structure is rigid —
auditors read these reports verbatim.
"""
from __future__ import annotations

from typing import Annotated, Literal, Optional

from pydantic import BaseModel, Field


ComplianceScope = Literal[
    "audit_chain",
    "phi_access",
    "evidence_gap",
    "flag_snapshot",
    "full",
]

ComplianceStatus = Literal["COMPLIANT", "REQUIRES_REVIEW", "NON_COMPLIANT"]


class ComplianceAnalysisInput(BaseModel):
    session_token: Annotated[str, Field(pattern=r"^[a-f0-9]{64}$")]
    scope: ComplianceScope = "full"
    period_days: int = Field(default=30, ge=1, le=365)
    custom_question: Optional[str] = Field(default=None, max_length=1000)


class ComplianceAnalysisOutput(BaseModel):
    model_id: str
    prompt_hash: str
    rationale: str
    confidence: float = Field(ge=0.0, le=1.0)
    status: ComplianceStatus = "REQUIRES_REVIEW"
    escalation_pending: bool = False
    evidence_refs: list[str] = []
    verification_issues: list[str] = []
