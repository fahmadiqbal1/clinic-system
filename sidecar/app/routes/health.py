import os

from fastapi import APIRouter, Depends
from fastapi.security import HTTPAuthorizationCredentials
from pydantic import BaseModel

from app.auth import security, verify_jwt

router = APIRouter()


class HealthResponse(BaseModel):
    status: str
    version: str


@router.get("/health", response_model=HealthResponse)
async def health() -> HealthResponse:
    return HealthResponse(status="ok", version="2.0.0")


@router.post("/v1/config/reload")
async def reload_config(
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> dict:
    """
    Hot-swap model provider without restarting the sidecar.
    Called by Laravel after the owner saves new model config in Platform Settings.
    Reads AI_MODEL_PROVIDER, OPENAI_API_KEY, ANTHROPIC_API_KEY, ONLINE_MODEL_ID
    from DB via clinic_ro and updates os.environ in-process.
    """
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    try:
        from app.services import db as db_service
        async with db_service.cursor() as cur:
            await cur.execute(
                "SELECT platform_name, meta FROM platform_settings "
                "WHERE provider = 'model_config'"
            )
            rows = await cur.fetchall()
    except Exception as exc:
        return {"reloaded": False, "error": str(exc),
                "provider": os.environ.get("AI_MODEL_PROVIDER", "ollama")}

    key_map = {
        "ai.model.provider":       "AI_MODEL_PROVIDER",
        "ai.model.online_model_id": "ONLINE_MODEL_ID",
        "ai.model.openai_key":     "OPENAI_API_KEY",
        "ai.model.anthropic_key":  "ANTHROPIC_API_KEY",
    }
    updated: list[str] = []
    for row in rows:
        env_key = key_map.get(row["platform_name"])
        if env_key:
            value = (row.get("meta") or {}).get("value", "")
            if value:
                os.environ[env_key] = value
                updated.append(env_key)

    # Reset harness singletons so they re-read env on next request
    from app.agent.harness_factory import HarnessFactory
    HarnessFactory.reset()

    provider = os.environ.get("AI_MODEL_PROVIDER", "ollama")
    return {"reloaded": True, "provider": provider, "updated_keys": updated}
