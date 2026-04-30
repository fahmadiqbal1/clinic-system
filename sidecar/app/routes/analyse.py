import os

from fastapi import APIRouter, Depends
from fastapi.security import HTTPAuthorizationCredentials
from pydantic import BaseModel

from app.auth import security, verify_jwt
from app.agent.model_provider import call_model

router = APIRouter()


class AnalyseRequest(BaseModel):
    system_prompt: str
    user_message: str


class AnalyseResponse(BaseModel):
    text: str


@router.post("/analyse", response_model=AnalyseResponse)
async def analyse_text(
    body: AnalyseRequest,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> AnalyseResponse:
    """
    Generic single-turn text analysis — routes through the active model provider.
    Used by Laravel for lab and radiology analyses so they benefit from the same
    provider hot-swap as clinical consultations.
    """
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    messages = [
        {"role": "system", "content": body.system_prompt},
        {"role": "user",   "content": body.user_message},
    ]

    ollama_url   = os.environ.get("OLLAMA_URL",   "http://127.0.0.1:8081")
    ollama_model = os.environ.get("OLLAMA_MODEL", "llama3.2:3b")

    text = await call_model(messages, ollama_url=ollama_url, ollama_model=ollama_model)
    return AnalyseResponse(text=text)
