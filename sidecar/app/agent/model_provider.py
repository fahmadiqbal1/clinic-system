"""
Model provider router — any provider, any model, all config from env at call time.

Provider and model names are read from os.environ on EVERY call so that
POST /v1/config/reload can hot-swap them without restarting the sidecar.

Supported providers (AI_MODEL_PROVIDER):
  "ollama"       — local Ollama  (OLLAMA_URL + OLLAMA_MODEL)
  "openai"       — OpenAI API    (OPENAI_BASE_URL optional, OPENAI_MODEL, OPENAI_API_KEY)
  "anthropic"    — Anthropic API (ANTHROPIC_MODEL, ANTHROPIC_API_KEY)
  "huggingface"  — HF Inference  (HF_MODEL, HF_API_KEY; HF_BASE_URL optional for dedicated endpoints)
  "groq"         — Groq API      (GROQ_MODEL, GROQ_API_KEY) — free tier, OpenAI-compatible

All *_MODEL and *_BASE_URL vars are set from the UI via POST /v1/config/reload.
No model name or URL is ever hardcoded.
"""
from __future__ import annotations

import logging
import os
from typing import Any

import httpx

logger = logging.getLogger(__name__)

ONLINE_TIMEOUT_S = 120.0


def _e(key: str, fallback: str = "") -> str:
    """Read env var, strip whitespace."""
    return os.environ.get(key, fallback).strip()


async def call_model(
    messages: list[dict],
    ollama_url: str,        # passed from harness — overridable via OLLAMA_URL
    ollama_model: str,      # passed from harness — overridable via OLLAMA_MODEL
    timeout_s: float = 600.0,
) -> str:
    provider = _e("AI_MODEL_PROVIDER", "ollama")
    logger.info("model_provider: provider=%s", provider)

    if provider == "openai":
        return await _call_openai(messages)
    if provider == "anthropic":
        return await _call_anthropic(messages)
    if provider == "huggingface":
        return await _call_huggingface(messages)
    if provider == "groq":
        return await _call_groq(messages)
    # default: ollama — env vars override whatever harness passed in
    url   = _e("OLLAMA_URL",   ollama_url)
    model = _e("OLLAMA_MODEL", ollama_model)
    return await _call_ollama(messages, url, model, timeout_s)


# ── Ollama ────────────────────────────────────────────────────────────────────

async def _call_ollama(messages: list[dict], url: str, model: str, timeout_s: float) -> str:
    if not url:
        raise RuntimeError("Ollama selected but OLLAMA_URL is not configured")
    if not model:
        raise RuntimeError("Ollama selected but OLLAMA_MODEL is not configured")

    async with httpx.AsyncClient(timeout=timeout_s) as client:
        resp = await client.post(
            f"{url.rstrip('/')}/v1/chat/completions",
            json={"model": model, "messages": messages, "max_tokens": 2048, "temperature": 0.1},
            headers={"bypass-tunnel-reminder": "true"},
        )
    if resp.status_code != 200:
        raise RuntimeError(f"Ollama {resp.status_code}: {resp.text[:300]}")
    return resp.json()["choices"][0]["message"]["content"]


# ── OpenAI (or any OpenAI-compatible endpoint) ────────────────────────────────

async def _call_openai(messages: list[dict]) -> str:
    api_key = _e("OPENAI_API_KEY")
    model   = _e("OPENAI_MODEL")
    base    = _e("OPENAI_BASE_URL", "https://api.openai.com/v1").rstrip("/")

    if not api_key:
        raise RuntimeError("OpenAI selected but OPENAI_API_KEY is not set")
    if not model:
        raise RuntimeError("OpenAI selected but OPENAI_MODEL is not set — enter a model name in Platform Settings")

    async with httpx.AsyncClient(timeout=ONLINE_TIMEOUT_S) as client:
        resp = await client.post(
            f"{base}/chat/completions",
            headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
            json={"model": model, "messages": messages, "max_tokens": 2048, "temperature": 0.1},
        )
    if resp.status_code != 200:
        raise RuntimeError(f"OpenAI {resp.status_code}: {resp.text[:300]}")
    return resp.json()["choices"][0]["message"]["content"]


# ── Anthropic ─────────────────────────────────────────────────────────────────

async def _call_anthropic(messages: list[dict]) -> str:
    api_key = _e("ANTHROPIC_API_KEY")
    model   = _e("ANTHROPIC_MODEL")

    if not api_key:
        raise RuntimeError("Anthropic selected but ANTHROPIC_API_KEY is not set")
    if not model:
        raise RuntimeError("Anthropic selected but ANTHROPIC_MODEL is not set — enter a model name in Platform Settings")

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
        "temperature": 0.1,
        "messages": user_messages,
    }
    if system_content:
        body["system"] = system_content

    async with httpx.AsyncClient(timeout=ONLINE_TIMEOUT_S) as client:
        resp = await client.post(
            "https://api.anthropic.com/v1/messages",
            headers={"x-api-key": api_key, "anthropic-version": "2023-06-01",
                     "Content-Type": "application/json"},
            json=body,
        )
    if resp.status_code != 200:
        raise RuntimeError(f"Anthropic {resp.status_code}: {resp.text[:300]}")
    return resp.json()["content"][0]["text"]


# ── Hugging Face Inference API (OpenAI-compatible /v1/ endpoint) ───────────────

async def _call_huggingface(messages: list[dict]) -> str:
    api_key = _e("HF_API_KEY")
    model   = _e("HF_MODEL")

    if not api_key:
        raise RuntimeError("Hugging Face selected but HF_API_KEY is not set")
    if not model:
        raise RuntimeError("Hugging Face selected but HF_MODEL is not set — enter a model name (e.g. mistralai/Mistral-7B-Instruct-v0.3) in Platform Settings")

    # Serverless Inference API: model embedded in path.
    # Dedicated Endpoint: custom HF_BASE_URL provided — use it directly.
    custom_base = _e("HF_BASE_URL", "").rstrip("/")
    default_base = "https://api-inference.huggingface.co/v1"
    if custom_base and custom_base != default_base:
        url = f"{custom_base}/v1/chat/completions"
    else:
        url = f"https://api-inference.huggingface.co/models/{model}/v1/chat/completions"
    async with httpx.AsyncClient(timeout=ONLINE_TIMEOUT_S) as client:
        resp = await client.post(
            url,
            headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
            json={"model": model, "messages": messages, "max_tokens": 2048, "temperature": 0.1},
        )
    if resp.status_code != 200:
        raise RuntimeError(f"HuggingFace {resp.status_code}: {resp.text[:300]}")
    return resp.json()["choices"][0]["message"]["content"]


# ── Groq (OpenAI-compatible, free tier) ───────────────────────────────────────

async def _call_groq(messages: list[dict]) -> str:
    api_key = _e("GROQ_API_KEY")
    model   = _e("GROQ_MODEL")

    if not api_key:
        raise RuntimeError("Groq selected but GROQ_API_KEY is not set")
    if not model:
        raise RuntimeError("Groq selected but GROQ_MODEL is not set — enter a model name (e.g. llama-3.1-8b-instant) in Platform Settings")

    async with httpx.AsyncClient(timeout=ONLINE_TIMEOUT_S) as client:
        resp = await client.post(
            "https://api.groq.com/openai/v1/chat/completions",
            headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
            json={"model": model, "messages": messages, "max_tokens": 2048, "temperature": 0.1},
        )
    if resp.status_code != 200:
        raise RuntimeError(f"Groq {resp.status_code}: {resp.text[:300]}")
    return resp.json()["choices"][0]["message"]["content"]
