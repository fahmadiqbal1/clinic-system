"""
Forecast endpoint tests.
DB calls are mocked — no real MySQL required.
"""
from __future__ import annotations

import base64
import hashlib
import hmac
import json
import time
from contextlib import asynccontextmanager
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from fastapi.testclient import TestClient

from app.main import app

SECRET = "test-secret-32-chars-long-minimum"
client = TestClient(app)


def _jwt(secret: str = SECRET) -> str:
    def b64u(data: bytes) -> str:
        return base64.urlsafe_b64encode(data).rstrip(b"=").decode()

    header = b64u(json.dumps({"alg": "HS256", "typ": "JWT"}).encode())
    body = b64u(json.dumps({"sub": 1, "role": "owner", "exp": int(time.time()) + 300}).encode())
    message = f"{header}.{body}".encode()
    sig = b64u(hmac.digest(secret.encode(), message, hashlib.sha256))
    return f"{header}.{body}.{sig}"


_MOCK_AUDIT_ROWS = [
    {"day": "2026-04-01", "events": 12},
    {"day": "2026-04-02", "events": 18},
    {"day": "2026-04-03", "events": 9},
]

_MOCK_INVENTORY_ROWS = [
    {"id": 1, "name": "Paracetamol 500mg", "department": "pharmacy",
     "unit": "tablet", "quantity_in_stock": 0, "minimum_stock_level": 100},
    {"id": 2, "name": "Gloves (L)", "department": "laboratory",
     "unit": "box", "quantity_in_stock": 5, "minimum_stock_level": 10},
    {"id": 3, "name": "Saline 500ml", "department": "radiology",
     "unit": "bag", "quantity_in_stock": 50, "minimum_stock_level": 20},
]


def _make_cursor_cm(rows):
    """Return a context-manager mock that yields a cursor returning rows."""
    cur = AsyncMock()
    cur.execute = AsyncMock()
    cur.fetchall = AsyncMock(return_value=rows)
    cm = MagicMock()
    cm.__aenter__ = AsyncMock(return_value=cur)
    cm.__aexit__ = AsyncMock(return_value=False)
    return cm


# ──────────────────────────────────────────────────────────────
# Revenue forecast
# ──────────────────────────────────────────────────────────────

def test_revenue_forecast_returns_historical_and_projected(monkeypatch):
    monkeypatch.setenv("SIDECAR_JWT_SECRET", SECRET)

    with patch("app.routes.forecast.db.cursor", return_value=_make_cursor_cm(_MOCK_AUDIT_ROWS)):
        resp = client.post(
            "/v1/forecast/revenue",
            json={"days_ahead": 7},
            headers={"Authorization": f"Bearer {_jwt()}"},
        )

    assert resp.status_code == 200
    data = resp.json()
    assert data["model_id"] == "revenue-ses-v1"
    assert len(data["forecast"]) > 0

    projected = [p for p in data["forecast"] if p["projected"]]
    historical = [p for p in data["forecast"] if not p["projected"]]
    assert len(projected) == 7
    assert len(historical) >= 3  # at least the 3 mocked audit days


def test_revenue_forecast_degrades_when_db_unconfigured(monkeypatch):
    monkeypatch.setenv("SIDECAR_JWT_SECRET", SECRET)

    async def _raise():
        raise RuntimeError("clinic_ro DB not configured")

    @asynccontextmanager
    async def _fail_cursor():
        raise RuntimeError("clinic_ro DB not configured")
        yield  # pragma: no cover

    with patch("app.routes.forecast.db.cursor", side_effect=_fail_cursor):
        resp = client.post(
            "/v1/forecast/revenue",
            json={"days_ahead": 14},
            headers={"Authorization": f"Bearer {_jwt()}"},
        )

    assert resp.status_code == 200
    assert resp.json()["forecast"] == []


# ──────────────────────────────────────────────────────────────
# Inventory forecast
# ──────────────────────────────────────────────────────────────

def test_inventory_forecast_classifies_status(monkeypatch):
    monkeypatch.setenv("SIDECAR_JWT_SECRET", SECRET)

    with patch("app.routes.forecast.db.cursor", return_value=_make_cursor_cm(_MOCK_INVENTORY_ROWS)):
        resp = client.post(
            "/v1/forecast/inventory",
            json={"days_ahead": 30},
            headers={"Authorization": f"Bearer {_jwt()}"},
        )

    assert resp.status_code == 200
    items = resp.json()["forecast"]
    assert len(items) == 3

    by_id = {i["id"]: i for i in items}
    assert by_id[1]["status"] == "critical"   # qty=0
    assert by_id[2]["status"] == "warning"    # qty=5 <= min=10
    assert by_id[3]["status"] == "ok"         # qty=50 > min=20


def test_inventory_forecast_degrades_when_db_unconfigured(monkeypatch):
    monkeypatch.setenv("SIDECAR_JWT_SECRET", SECRET)

    @asynccontextmanager
    async def _fail_cursor():
        raise RuntimeError("clinic_ro DB not configured")
        yield  # pragma: no cover

    with patch("app.routes.forecast.db.cursor", side_effect=_fail_cursor):
        resp = client.post(
            "/v1/forecast/inventory",
            json={"days_ahead": 30},
            headers={"Authorization": f"Bearer {_jwt()}"},
        )

    assert resp.status_code == 200
    assert resp.json()["forecast"] == []


def test_forecast_rejects_invalid_jwt(monkeypatch):
    monkeypatch.setenv("SIDECAR_JWT_SECRET", SECRET)
    resp = client.post(
        "/v1/forecast/revenue",
        json={"days_ahead": 7},
        headers={"Authorization": "Bearer bad.token.here"},
    )
    assert resp.status_code == 401
