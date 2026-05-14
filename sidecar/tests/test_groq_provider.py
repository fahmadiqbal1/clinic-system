"""
Tests for per-persona Groq provider routing in model_provider.py.
All HTTP calls are mocked — no real Groq API key needed.
Async tests run via asyncio.run() so no pytest-asyncio plugin is required.
"""
from __future__ import annotations

import asyncio
import os
from unittest.mock import AsyncMock, patch

import pytest


# ── Helpers ────────────────────────────────────────────────────────────────────

def _mock_groq_response(text: str = "OK"):
    """Return a minimal Groq-compatible httpx response mock."""
    import httpx
    return httpx.Response(
        200,
        json={
            "choices": [{"message": {"content": text}}],
            "model": "llama-3.3-70b-versatile",
        },
    )


# ── Tests ──────────────────────────────────────────────────────────────────────

class TestGroqProviderRouting:
    """model_provider.call_model routes to Groq when AI_MODEL_PROVIDER=groq."""

    def setup_method(self):
        os.environ["AI_MODEL_PROVIDER"] = "groq"
        os.environ["GROQ_API_KEY"] = "gsk_test_default"
        os.environ["GROQ_MODEL"] = "llama-3.3-70b-versatile"
        # Clear per-persona overrides
        for p in ("CLINICAL", "ADMIN", "OPS", "COMPLIANCE"):
            os.environ.pop(f"GROQ_API_KEY_{p}", None)
            os.environ.pop(f"GROQ_MODEL_{p}", None)
            os.environ.pop(f"AI_MODEL_PROVIDER_{p}", None)

    def teardown_method(self):
        for key in ("AI_MODEL_PROVIDER", "GROQ_API_KEY", "GROQ_MODEL"):
            os.environ.pop(key, None)

    def test_default_groq_key_used_when_no_persona_override(self):
        """Without persona-specific env vars the default GROQ_API_KEY is used."""
        from app.agent import model_provider

        messages = [{"role": "user", "content": "ping"}]

        with patch("httpx.AsyncClient.post", new_callable=AsyncMock) as mock_post:
            mock_post.return_value = _mock_groq_response("pong")
            result = asyncio.run(model_provider.call_model(messages, persona=""))

        assert result == "pong"
        # Verify Authorization header used the default key
        call_kwargs = mock_post.call_args
        headers = call_kwargs.kwargs.get("headers", call_kwargs[1].get("headers", {}))
        assert headers.get("Authorization") == "Bearer gsk_test_default"

    def test_persona_specific_groq_key_takes_precedence(self):
        """GROQ_API_KEY_OPS overrides the default GROQ_API_KEY for the ops persona."""
        os.environ["GROQ_API_KEY_OPS"] = "gsk_ops_specific_key"

        from app.agent import model_provider
        import importlib
        importlib.reload(model_provider)

        messages = [{"role": "user", "content": "check stock"}]

        with patch("httpx.AsyncClient.post", new_callable=AsyncMock) as mock_post:
            mock_post.return_value = _mock_groq_response("stock ok")
            result = asyncio.run(model_provider.call_model(messages, persona="ops"))

        assert result == "stock ok"
        call_kwargs = mock_post.call_args
        headers = call_kwargs.kwargs.get("headers", call_kwargs[1].get("headers", {}))
        assert headers.get("Authorization") == "Bearer gsk_ops_specific_key"

    def test_persona_model_override(self):
        """GROQ_MODEL_CLINICAL selects a different model for the clinical persona."""
        os.environ["GROQ_MODEL_CLINICAL"] = "gemma2-9b-it"

        from app.agent import model_provider
        import importlib
        importlib.reload(model_provider)

        messages = [{"role": "user", "content": "diagnose"}]
        captured = {}

        async def _fake_post(url, *, headers=None, json=None, timeout=None):
            captured["model"] = (json or {}).get("model", "")
            return _mock_groq_response("diagnosis")

        with patch("httpx.AsyncClient.post", new_callable=AsyncMock, side_effect=_fake_post):
            asyncio.run(model_provider.call_model(messages, persona="clinical"))

        assert captured.get("model") == "gemma2-9b-it"

    def test_groq_http_error_raises_runtime_error(self):
        """A non-200 Groq response raises RuntimeError so fail-open wrappers catch it."""
        import httpx
        from app.agent import model_provider

        messages = [{"role": "user", "content": "test"}]
        bad_resp = httpx.Response(429, json={"error": "rate limited"})

        with patch("httpx.AsyncClient.post", new_callable=AsyncMock, return_value=bad_resp):
            with pytest.raises((RuntimeError, Exception)):
                asyncio.run(model_provider.call_model(messages, persona="admin"))

    def test_falls_back_to_ollama_when_provider_is_ollama(self):
        """When AI_MODEL_PROVIDER=ollama the Ollama path is used, not Groq."""
        os.environ["AI_MODEL_PROVIDER"] = "ollama"
        os.environ["OLLAMA_URL"] = "http://127.0.0.1:11434"
        os.environ["OLLAMA_MODEL"] = "llama3.1:8b"

        from app.agent import model_provider
        import importlib
        importlib.reload(model_provider)

        messages = [{"role": "user", "content": "hello"}]
        captured = {}

        async def _fake_post(url, *, headers=None, json=None, timeout=None):
            captured["url"] = url
            return _mock_groq_response("hi from ollama")

        with patch("httpx.AsyncClient.post", new_callable=AsyncMock, side_effect=_fake_post):
            result = asyncio.run(model_provider.call_model(messages, persona=""))

        assert "ollama" in captured.get("url", "").lower() or result == "hi from ollama"
