"""
Administrative AI — request/response schemas.

The admin persona answers owner questions about revenue, discounts, FBR
compliance, and staff payout integrity. It NEVER references patient data.
"""
from __future__ import annotations

from typing import Annotated, Literal, Optional

from pydantic import BaseModel, Field


AdminQueryType = Literal[
    "revenue_anomaly",
    "discount_risk",
    "fbr_status",
    "payout_audit",
    "general",
]

PriorityLevel = Literal["Critical", "High", "Medium", "Low"]


class AdminAnalysisInput(BaseModel):
    # case_token-shaped session id so StateStore can dedupe per-owner-session
    session_token: Annotated[str, Field(pattern=r"^[a-f0-9]{64}$")]
    query_type: AdminQueryType = "general"
    period_days: int = Field(default=7, ge=1, le=365)
    custom_question: Optional[str] = Field(default=None, max_length=1000)


class AdminAnalysisOutput(BaseModel):
    model_id: str
    prompt_hash: str
    rationale: str
    confidence: float = Field(ge=0.0, le=1.0)
    priority: PriorityLevel = "Medium"
    requires_human_review: bool = True
    action_items: list[str] = []
    verification_issues: list[str] = []
