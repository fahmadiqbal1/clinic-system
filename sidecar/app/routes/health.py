import os

from fastapi import APIRouter
from pydantic import BaseModel

router = APIRouter()


class HealthResponse(BaseModel):
    status: str
    version: str


@router.get("/health", response_model=HealthResponse)
async def health() -> HealthResponse:
    return HealthResponse(status="ok", version="2.0.0")


@router.get("/debug/env")
async def debug_env() -> dict:
    return {
        "OLLAMA_URL": os.environ.get("OLLAMA_URL", "NOT SET"),
        "OLLAMA_MODEL_PERSONA": os.environ.get("OLLAMA_MODEL_PERSONA", "NOT SET"),
        "SIDECAR_JWT_SECRET_set": bool(os.environ.get("SIDECAR_JWT_SECRET")),
        "CLINIC_RO_HOST": os.environ.get("CLINIC_RO_HOST", "NOT SET"),
        "CLINIC_RO_PASSWORD_set": bool(os.environ.get("CLINIC_RO_PASSWORD")),
    }
