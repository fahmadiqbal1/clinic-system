"""
Document classification endpoint.

POST /v1/classify-document
  Accepts up to 1000 chars of document text + optional filename hint.
  Routes to Groq (or configured provider) and returns:
    { type: price_list|mou|lab_referral|invoice|contract|unknown,
      confidence: 0.0–1.0,
      reason: str }

Used by ProcessPriceListJob to decide how to process an uploaded file
before committing to price extraction, MOU ingestion, or manual review.
"""
from __future__ import annotations

import json
import logging
import os
import re

from fastapi import APIRouter, Depends
from fastapi.security import HTTPAuthorizationCredentials
from pydantic import BaseModel, Field, field_validator

from app.auth import security, verify_jwt
from app.agent.model_provider import call_model

logger = logging.getLogger(__name__)
router = APIRouter()

_VALID_TYPES = {"price_list", "mou", "lab_referral", "invoice", "contract", "unknown"}

_SYSTEM_PROMPT = """\
You are a document classifier for a medical clinic ERP. \
Classify the document excerpt provided by the user into EXACTLY ONE of these types:
- price_list: A vendor price list or drug catalogue (contains item names and prices)
- mou: A Memorandum of Understanding, partnership agreement, or supplier contract with terms
- lab_referral: A lab test referral form or lab requisition
- invoice: A sales or purchase invoice (has invoice number, totals, tax)
- contract: A legal or service contract (not an MOU; contains SLAs, penalty clauses)
- unknown: Cannot be determined from the excerpt

Respond with valid JSON only — no markdown, no explanation outside the JSON:
{"type": "<type>", "confidence": <0.0-1.0>, "reason": "<one sentence>"}"""


class ClassifyRequest(BaseModel):
    text: str = Field(..., min_length=10, max_length=1200)
    filename: str = Field(default="", max_length=255)

    @field_validator("text")
    @classmethod
    def strip_text(cls, v: str) -> str:
        return v[:1000]


class ClassifyResponse(BaseModel):
    type: str
    confidence: float
    reason: str


def _parse_response(raw: str) -> dict:
    """Extract JSON from model output, stripping any markdown fences."""
    raw = raw.strip()
    # Strip markdown code fence if present
    raw = re.sub(r"^```(?:json)?\s*", "", raw)
    raw = re.sub(r"\s*```$", "", raw)
    try:
        parsed = json.loads(raw)
    except json.JSONDecodeError:
        # Try to extract JSON object from surrounding prose
        m = re.search(r'\{[^{}]+\}', raw, re.DOTALL)
        if m:
            parsed = json.loads(m.group())
        else:
            raise ValueError(f"No JSON found in model output: {raw[:200]}")
    return parsed


@router.post("/classify-document", response_model=ClassifyResponse)
async def classify_document(
    body: ClassifyRequest,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> ClassifyResponse:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    user_message = body.text
    if body.filename:
        user_message = f"Filename: {body.filename}\n\n{user_message}"

    messages = [
        {"role": "system", "content": _SYSTEM_PROMPT},
        {"role": "user",   "content": user_message},
    ]

    try:
        raw = await call_model(messages, persona="classify")
        parsed = _parse_response(raw)
        doc_type   = parsed.get("type", "unknown")
        confidence = float(parsed.get("confidence", 0.5))
        reason     = str(parsed.get("reason", ""))

        if doc_type not in _VALID_TYPES:
            doc_type = "unknown"
            confidence = min(confidence, 0.4)

    except Exception as exc:
        logger.warning("classify_document: model call failed (%s) — defaulting to unknown", exc)
        doc_type   = "unknown"
        confidence = 0.0
        reason     = "Classification unavailable."

    return ClassifyResponse(type=doc_type, confidence=confidence, reason=reason)
