"""
Model provider router — Ollama (offline) | OpenAI | Anthropic (online).

Provider is read from os.environ["AI_MODEL_PROVIDER"] on every call so that
a /v1/config/reload can hot-swap it without restarting the sidecar.

Supported values:
  "ollama"     — local Ollama instance (default / offline mode)
  "openai"     — OpenAI Chat Completions API
  "anthropic"  — Anthropic Messages API
"""
from __future__ import annotations

import logging
import os
from typing import Any

import httpx

logger = logging.getLogger(__name__)

ONLINE_TIMEOUT_S = 120.0


def _provider() -> str:
    return os.environ.get("AI_MODEL_PROVIDER", "ollama").lower()


def _online_model_id(fallback: str) -> str:
    return os.environ.get("ONLINE_MODEL_ID", fallback)


async def call_model(
    messages: list[dict],
    ollama_url: str,
    ollama_model: str,
    timeout_s: float = 600.0,
) -> str:
    provider = _provider()
    logger.info("model_provider: using provider=%s", provider)

    if provider == "openai":
        return await _call_openai(messages)
    if provider == "anthropic":
        return await _call_anthropic(messages)
    return await _call_ollama(messages, ollama_url, ollama_model, timeout_s)


# ── Ollama ────────────────────────────────────────────────────────────────────

async def _call_ollama(
    messages: list[dict], url: str, model: str, timeout_s: float
) -> str:
    async with httpx.AsyncClient(timeout=timeout_s) as client:
        resp = await client.post(
            f"{url}/v1/chat/completions",
            json={"model": model, "messages": messages, "max_tokens": 2048, "temperature": 0.3},
            headers={"bypass-tunnel-reminder": "true"},
        )
    if resp.status_code != 200:
        raise RuntimeError(f"Ollama {resp.status_code}: {resp.text[:200]}")
    return resp.json()["choices"][0]["message"]["content"]


# ── OpenAI ────────────────────────────────────────────────────────────────────

async def _call_openai(messages: list[dict]) -> str:
    api_key = os.environ.get("OPENAI_API_KEY", "")
    if not api_key:
        raise RuntimeError("OpenAI provider selected but OPENAI_API_KEY is not set")

    model = _online_model_id("gpt-4o-mini")

    async with httpx.AsyncClient(timeout=ONLINE_TIMEOUT_S) as client:
        resp = await client.post(
            "https://api.openai.com/v1/chat/completions",
            headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
            json={"model": model, "messages": messages, "max_tokens": 2048, "temperature": 0.3},
        )

    if resp.status_code != 200:
        raise RuntimeError(f"OpenAI {resp.status_code}: {resp.text[:300]}")

    return resp.json()["choices"][0]["message"]["content"]


# ── Anthropic ─────────────────────────────────────────────────────────────────

async def _call_anthropic(messages: list[dict]) -> str:
    api_key = os.environ.get("ANTHROPIC_API_KEY", "")
    if not api_key:
        raise RuntimeError("Anthropic provider selected but ANTHROPIC_API_KEY is not set")

    model = _online_model_id("claude-haiku-4-5-20251001")

    # Anthropic separates the system prompt from the conversation turns
    system_content = ""
    user_messages: list[dict[str, Any]] = []
    for m in messages:
        if m["role"] == "system":
            system_content = m["content"]
        else:
            user_messages.append({"role": m["role"], "content": m["content"]})

    body: dict[str, Any] = {
        "model": model,
        "max_tokens": 2048,
        "temperature": 0.3,
        "messages": user_messages,
    }
    if system_content:
        body["system"] = system_content

    async with httpx.AsyncClient(timeout=ONLINE_TIMEOUT_S) as client:
        resp = await client.post(
            "https://api.anthropic.com/v1/messages",
            headers={
                "x-api-key": api_key,
                "anthropic-version": "2023-06-01",
                "Content-Type": "application/json",
            },
            json=body,
        )

    if resp.status_code != 200:
        raise RuntimeError(f"Anthropic {resp.status_code}: {resp.text[:300]}")

    return resp.json()["content"][0]["text"]
