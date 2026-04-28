"""
/v1/consult — AI consultation endpoint.

Routes through the ETCSLV AgentHarness (Agent = Model + Harness).
The harness manages: context budgeting (C), RAGFlow pre-fetch (T+E),
state persistence (S), lifecycle logging (L), output verification (V).
"""
from __future__ import annotations

import os

from fastapi import APIRouter, Depends, HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.agent.harness import get_harness
from app.auth import security, verify_jwt
from app.schemas.medical import ConsultInput, ConsultOutput

router = APIRouter()


@router.post("/consult", response_model=ConsultOutput)
async def consult(
    body: ConsultInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> ConsultOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    try:
        return await get_harness().run(body)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
