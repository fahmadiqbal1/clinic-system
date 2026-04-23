import base64
import hashlib
import hmac
import json
import time

import pytest
from fastapi import HTTPException

from app.auth import verify_jwt

SECRET = "test-secret-32-chars-long-minimum"


def _make_token(payload: dict, secret: str = SECRET) -> str:
    def b64u(data: bytes) -> str:
        return base64.urlsafe_b64encode(data).rstrip(b"=").decode()

    header = b64u(json.dumps({"alg": "HS256", "typ": "JWT"}).encode())
    body = b64u(json.dumps(payload).encode())
    message = f"{header}.{body}".encode()
    sig = b64u(hmac.digest(secret.encode(), message, hashlib.sha256))
    return f"{header}.{body}.{sig}"


def test_valid_token_returns_payload():
    token = _make_token({"sub": 1, "role": "doctor", "exp": int(time.time()) + 300})
    payload = verify_jwt(token, SECRET)
    assert payload["sub"] == 1
    assert payload["role"] == "doctor"


def test_expired_token_raises_401():
    token = _make_token({"sub": 1, "exp": int(time.time()) - 1})
    with pytest.raises(HTTPException) as exc_info:
        verify_jwt(token, SECRET)
    assert exc_info.value.status_code == 401


def test_tampered_signature_raises_401():
    token = _make_token({"sub": 1, "exp": int(time.time()) + 300})
    parts = token.split(".")
    tampered = parts[0] + "." + parts[1] + "." + parts[2] + "X"
    with pytest.raises(HTTPException) as exc_info:
        verify_jwt(tampered, SECRET)
    assert exc_info.value.status_code == 401


def test_wrong_secret_raises_401():
    token = _make_token({"sub": 1, "exp": int(time.time()) + 300}, secret="wrong-secret")
    with pytest.raises(HTTPException) as exc_info:
        verify_jwt(token, SECRET)
    assert exc_info.value.status_code == 401


def test_malformed_token_raises_401():
    with pytest.raises(HTTPException):
        verify_jwt("not.a.valid.jwt.at.all", SECRET)
