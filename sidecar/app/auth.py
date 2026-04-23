import base64
import hashlib
import hmac
import json
import time

from fastapi import HTTPException
from fastapi.security import HTTPBearer

security = HTTPBearer()


def _b64url_decode(s: str) -> bytes:
    padding = (4 - len(s) % 4) % 4
    return base64.urlsafe_b64decode(s + "=" * padding)


def verify_jwt(token: str, secret: str) -> dict:
    """Verify a HS256 JWT minted by AiSidecarClient::mintJwt()."""
    parts = token.split(".")
    if len(parts) != 3:
        raise HTTPException(status_code=401, detail="Invalid token format")

    header_b64, payload_b64, sig_b64 = parts
    message = f"{header_b64}.{payload_b64}".encode()

    expected = base64.urlsafe_b64encode(
        hmac.digest(secret.encode(), message, hashlib.sha256)
    ).rstrip(b"=").decode()

    if not hmac.compare_digest(expected, sig_b64):
        raise HTTPException(status_code=401, detail="Invalid token signature")

    payload = json.loads(_b64url_decode(payload_b64))
    if payload.get("exp", 0) < time.time():
        raise HTTPException(status_code=401, detail="Token expired")

    return payload
