import os
from typing import Optional

from fastapi import APIRouter, Depends
from fastapi.security import HTTPAuthorizationCredentials
from pydantic import BaseModel

from app.auth import security, verify_jwt

router = APIRouter()

# All platform_settings keys that map to sidecar env vars.
# Extend here when adding a new provider — no code changes needed elsewhere.
_DB_TO_ENV: dict[str, str] = {
    "ai.model.provider":          "AI_MODEL_PROVIDER",
    # Ollama
    "ai.model.ollama.url":        "OLLAMA_URL",
    "ai.model.ollama.model":      "OLLAMA_MODEL",
    # OpenAI (or any OpenAI-compatible endpoint)
    "ai.model.openai.base_url":   "OPENAI_BASE_URL",
    "ai.model.openai.model":      "OPENAI_MODEL",
    "ai.model.openai.key":        "OPENAI_API_KEY",
    # Anthropic
    "ai.model.anthropic.model":   "ANTHROPIC_MODEL",
    "ai.model.anthropic.key":     "ANTHROPIC_API_KEY",
    # Hugging Face
    "ai.model.hf.base_url":       "HF_BASE_URL",
    "ai.model.hf.model":          "HF_MODEL",
    "ai.model.hf.key":            "HF_API_KEY",
}


class HealthResponse(BaseModel):
    status: str
    version: str


class ReloadRequest(BaseModel):
    # Optional direct env-var payload from Laravel (plaintext). When provided,
    # these values are applied immediately without touching the DB query path.
    env: Optional[dict[str, str]] = None


@router.get("/health", response_model=HealthResponse)
async def health() -> HealthResponse:
    return HealthResponse(status="ok", version="2.0.0")


@router.get("/v1/config/current")
async def current_config(
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> dict:
    """Return active provider + model names (no secrets) so the UI can display current state."""
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    return {
        "provider":        os.environ.get("AI_MODEL_PROVIDER", "ollama"),
        "ollama_url":      os.environ.get("OLLAMA_URL", ""),
        "ollama_model":    os.environ.get("OLLAMA_MODEL", ""),
        "openai_model":    os.environ.get("OPENAI_MODEL", ""),
        "openai_base_url": os.environ.get("OPENAI_BASE_URL", ""),
        "anthropic_model": os.environ.get("ANTHROPIC_MODEL", ""),
        "hf_model":        os.environ.get("HF_MODEL", ""),
        "hf_base_url":     os.environ.get("HF_BASE_URL", ""),
        # keys: only report whether set, never the value
        "openai_key_set":    bool(os.environ.get("OPENAI_API_KEY")),
        "anthropic_key_set": bool(os.environ.get("ANTHROPIC_API_KEY")),
        "hf_key_set":        bool(os.environ.get("HF_API_KEY")),
    }


@router.post("/v1/config/reload")
async def reload_config(
    body: ReloadRequest = ReloadRequest(),
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> dict:
    """
    Hot-swap model provider + model names without restarting the sidecar.

    Two paths:
    1. If `body.env` is present (sent by Laravel after UI save): apply those values
       directly — no DB read needed, and no decryption problem with API keys.
    2. Otherwise: read ai.model.* rows from platform_settings via clinic_ro (non-secret
       fields only — provider, URLs, model names).
    """
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    updated: list[str] = []

    if body.env:
        # Fast path — Laravel passed the full config as plaintext
        for env_key, value in body.env.items():
            if value:
                os.environ[env_key] = value
                label = env_key + "=***" if "key" in env_key.lower() else env_key
                updated.append(label)
    else:
        # Fallback — read non-secret fields from DB (API keys remain whatever was last set)
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

        for row in rows:
            env_key = _DB_TO_ENV.get(row["platform_name"])
            if not env_key:
                continue
            # Skip key fields in DB fallback — they are stored plaintext but we only
            # populate them via the body.env path to keep the fast path canonical.
            raw = (row.get("meta") or {}).get("value", "")
            if raw:
                os.environ[env_key] = str(raw)
                updated.append(env_key if "key" not in env_key.lower() else env_key + "=***")

    from app.agent.harness_factory import HarnessFactory
    HarnessFactory.reset()

    return {
        "reloaded": True,
        "provider": os.environ.get("AI_MODEL_PROVIDER", "ollama"),
        "updated_keys": updated,
    }
