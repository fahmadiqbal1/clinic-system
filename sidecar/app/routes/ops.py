"""POST /v1/ops/analyse — operations AI persona endpoint."""
from __future__ import annotations

import os

from fastapi import APIRouter, Depends, HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.agent.harness_factory import HarnessFactory
from app.auth import security, verify_jwt
from app.schemas.ops import OpsAnalysisInput, OpsAnalysisOutput

router = APIRouter()


@router.post("/ops/analyse", response_model=OpsAnalysisOutput)
async def ops_analyse(
    body: OpsAnalysisInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> OpsAnalysisOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    try:
        result = await HarnessFactory.ops().run(body)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc

    return OpsAnalysisOutput(
        model_id=result["model_id"],
        prompt_hash=result["prompt_hash"],
        rationale=result["rationale"],
        confidence=result["confidence"],
        urgency=result.get("urgency", "Info"),
        critical_items=result.get("critical_items", []),
        action_items=result.get("action_items", []),
        verification_issues=result["verification_issues"],
    )
