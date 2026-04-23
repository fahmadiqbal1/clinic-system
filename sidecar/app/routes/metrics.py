"""
Prometheus /metrics endpoint (Phase 5).
No JWT auth — Prometheus scrapers call this directly.
Counters and histograms are populated by PrometheusMiddleware in main.py.
"""

from fastapi import APIRouter, Response
from prometheus_client import (
    Counter,
    Histogram,
    generate_latest,
    CONTENT_TYPE_LATEST,
    REGISTRY,
)

router = APIRouter()

REQUEST_COUNT = Counter(
    "sidecar_requests_total",
    "Total HTTP requests to the sidecar",
    ["method", "endpoint", "status_code"],
)

REQUEST_DURATION = Histogram(
    "sidecar_request_duration_seconds",
    "HTTP request duration in seconds",
    ["method", "endpoint"],
    buckets=[0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 15.0],
)


@router.get("/metrics", include_in_schema=False)
async def prometheus_metrics() -> Response:
    return Response(generate_latest(REGISTRY), media_type=CONTENT_TYPE_LATEST)
