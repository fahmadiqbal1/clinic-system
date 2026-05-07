"""
Operations AI — request/response schemas.

The ops persona answers questions about inventory health, procurement,
expense category trends, and queue/system health.
"""
from __future__ import annotations

from typing import Annotated, Literal, Optional

from pydantic import BaseModel, Field


OpsDomain = Literal["inventory", "procurement", "expense", "queue", "rooms", "general"]
UrgencyLevel = Literal["Critical", "Warning", "Info"]


class OpsAnalysisInput(BaseModel):
    session_token: Annotated[str, Field(pattern=r"^[a-f0-9]{64}$")]
    domain: OpsDomain = "general"
    period_days: int = Field(default=30, ge=1, le=365)
    custom_question: Optional[str] = Field(default=None, max_length=1000)


class OpsAnalysisOutput(BaseModel):
    model_id: str
    prompt_hash: str
    rationale: str
    confidence: float = Field(ge=0.0, le=1.0)
    urgency: UrgencyLevel = "Info"
    critical_items: list[str] = []
    action_items: list[str] = []
    verification_issues: list[str] = []
