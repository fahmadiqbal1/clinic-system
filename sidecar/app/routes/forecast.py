"""
Forecast endpoints — Phase 6 implementations.

Revenue: exponential smoothing over audit_log event counts (90-day history).
Inventory: threshold analysis from inventory_items (quantity_in_stock vs minimum_stock_level).

Both degrade gracefully when clinic_ro DB is not configured — return empty forecast.
"""
from __future__ import annotations

import os
from datetime import datetime, timedelta, timezone

from fastapi import APIRouter, Depends
from fastapi.security import HTTPAuthorizationCredentials

from app.auth import security, verify_jwt
from app.schemas.medical import ForecastInput, ForecastOutput
from app.services import db

router = APIRouter()

_ALPHA = 0.3  # exponential smoothing weight


def _ses(series: list[float]) -> list[float]:
    """Single exponential smoothing — returns smoothed series of same length."""
    if not series:
        return []
    result = [series[0]]
    for v in series[1:]:
        result.append(_ALPHA * v + (1.0 - _ALPHA) * result[-1])
    return result


async def _revenue_data(days_ahead: int) -> list[dict]:
    try:
        async with db.cursor() as cur:
            await cur.execute(
                """
                SELECT DATE(created_at) AS day, COUNT(*) AS events
                FROM audit_logs
                WHERE created_at >= NOW() - INTERVAL 90 DAY
                GROUP BY DATE(created_at)
                ORDER BY day
                """
            )
            rows = await cur.fetchall()
    except RuntimeError:
        return []

    today = datetime.now(timezone.utc).date()
    start = today - timedelta(days=90)
    by_day: dict = {row["day"]: int(row["events"]) for row in rows}

    raw: list[float] = []
    labels: list[str] = []
    d = start
    while d <= today:
        raw.append(float(by_day.get(d, 0)))
        labels.append(d.isoformat())
        d += timedelta(days=1)

    smoothed = _ses(raw)
    last_level = smoothed[-1] if smoothed else 0.0

    result = [
        {"day": labels[i], "events": int(raw[i]), "projected": False}
        for i in range(len(labels))
    ]
    for i in range(days_ahead):
        d = today + timedelta(days=i + 1)
        result.append({"day": d.isoformat(), "events": round(last_level), "projected": True})

    return result


async def _inventory_data() -> list[dict]:
    try:
        async with db.cursor() as cur:
            await cur.execute(
                """
                SELECT id, name, department, unit,
                       quantity_in_stock, minimum_stock_level
                FROM inventory_items
                WHERE deleted_at IS NULL AND is_active = 1
                ORDER BY (quantity_in_stock / GREATEST(minimum_stock_level, 1)) ASC
                LIMIT 200
                """
            )
            rows = await cur.fetchall()
    except RuntimeError:
        return []

    result = []
    for row in rows:
        qty = int(row["quantity_in_stock"] or 0)
        min_lvl = int(row["minimum_stock_level"] or 0)
        if qty == 0:
            status = "critical"
        elif min_lvl > 0 and qty <= min_lvl:
            status = "warning"
        else:
            status = "ok"
        result.append({
            "id": row["id"],
            "name": row["name"],
            "department": row["department"],
            "unit": row["unit"],
            "quantity_in_stock": qty,
            "minimum_stock_level": min_lvl,
            "status": status,
        })
    return result


@router.post("/forecast/revenue", response_model=ForecastOutput)
async def forecast_revenue(
    body: ForecastInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> ForecastOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    return ForecastOutput(
        forecast=await _revenue_data(body.days_ahead),
        model_id="revenue-ses-v1",
        generated_at=datetime.now(timezone.utc).isoformat(),
    )


@router.post("/forecast/inventory", response_model=ForecastOutput)
async def forecast_inventory(
    body: ForecastInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> ForecastOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    return ForecastOutput(
        forecast=await _inventory_data(),
        model_id="inventory-threshold-v1",
        generated_at=datetime.now(timezone.utc).isoformat(),
    )
