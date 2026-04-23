"""
Forecast endpoints — stub for Phase 2.
Full MySQL read-only trend analysis via clinic_ro user arrives in Phase 5.
"""

import os
from datetime import datetime, timezone

from fastapi import APIRouter, Depends
from fastapi.security import HTTPAuthorizationCredentials

from app.auth import security, verify_jwt
from app.schemas.medical import ForecastInput, ForecastOutput

router = APIRouter()


@router.post("/forecast/revenue", response_model=ForecastOutput)
async def forecast_revenue(
    body: ForecastInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> ForecastOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    return ForecastOutput(
        forecast=[],
        model_id="revenue-stub",
        generated_at=datetime.now(timezone.utc).isoformat(),
    )


@router.post("/forecast/inventory", response_model=ForecastOutput)
async def forecast_inventory(
    body: ForecastInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> ForecastOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    return ForecastOutput(
        forecast=[],
        model_id="inventory-stub",
        generated_at=datetime.now(timezone.utc).isoformat(),
    )
