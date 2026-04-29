"""POST /v1/compliance/analyse — compliance AI persona endpoint."""
from __future__ import annotations

import os

from fastapi import APIRouter, Depends, HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.agent.harness_factory import HarnessFactory
from app.auth import security, verify_jwt
from app.schemas.compliance import (
    ComplianceAnalysisInput,
    ComplianceAnalysisOutput,
)

router = APIRouter()


@router.post("/compliance/analyse", response_model=ComplianceAnalysisOutput)
async def compliance_analyse(
    body: ComplianceAnalysisInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> ComplianceAnalysisOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    try:
        result = await HarnessFactory.compliance().run(body)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc

    return ComplianceAnalysisOutput(
        model_id=result["model_id"],
        prompt_hash=result["prompt_hash"],
        rationale=result["rationale"],
        confidence=result["confidence"],
        status=result.get("status", "REQUIRES_REVIEW"),
        escalation_pending=bool(result.get("escalation_pending", False)),
        evidence_refs=result.get("evidence_refs", []),
        verification_issues=result["verification_issues"],
    )
