"""POST /v1/admin/analyse — administrative AI persona endpoint."""
from __future__ import annotations

import os

from fastapi import APIRouter, Depends, HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.agent.harness_factory import HarnessFactory
from app.auth import security, verify_jwt
from app.schemas.admin import AdminAnalysisInput, AdminAnalysisOutput

router = APIRouter()


@router.post("/admin/analyse", response_model=AdminAnalysisOutput)
async def admin_analyse(
    body: AdminAnalysisInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> AdminAnalysisOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    try:
        result = await HarnessFactory.admin().run(body)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc

    return AdminAnalysisOutput(
        model_id=result["model_id"],
        prompt_hash=result["prompt_hash"],
        rationale=result["rationale"],
        confidence=result["confidence"],
        priority=result.get("priority", "Medium"),
        requires_human_review=result["requires_human_review"],
        action_items=result.get("action_items", []),
        verification_issues=result["verification_issues"],
    )
