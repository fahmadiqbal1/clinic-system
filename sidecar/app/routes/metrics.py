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

# ── Agent-level metrics (Phase 8A — populated by lifecycle hooks) ────────────
AGENT_INVOCATIONS = Counter(
    "agent_invocations_total",
    "ETCSLV agent invocations grouped by agent persona and confidence band",
    ["agent", "confidence_band"],
)

AGENT_DURATION = Histogram(
    "agent_duration_seconds",
    "ETCSLV agent end-to-end run duration in seconds",
    ["agent"],
    buckets=[0.5, 1.0, 2.5, 5.0, 10.0, 20.0, 45.0, 90.0],
)

AGENT_LOW_CONFIDENCE = Counter(
    "agent_low_confidence_total",
    "ETCSLV agent runs that emitted confidence below alerting threshold",
    ["agent"],
)

AGENT_TOOL_CALLS = Counter(
    "agent_tool_calls_total",
    "ETCSLV tool invocations grouped by tool and outcome",
    ["agent", "tool", "outcome"],
)


@router.get("/metrics", include_in_schema=False)
async def prometheus_metrics() -> Response:
    return Response(generate_latest(REGISTRY), media_type=CONTENT_TYPE_LATEST)
