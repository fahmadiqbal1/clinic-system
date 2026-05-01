import logging
import os
import time
from contextlib import asynccontextmanager
from pathlib import Path

logger = logging.getLogger(__name__)

# Load the project root .env so native (non-Docker) startup has all secrets.
# Runs before any route import so SIDECAR_JWT_SECRET is available immediately.
try:
    from dotenv import load_dotenv
    # Resolve to absolute path so this works regardless of cwd when uvicorn starts.
    _env_file = Path(__file__).resolve().parent.parent.parent / ".env"
    load_dotenv(_env_file, override=False)
except ImportError:
    pass  # python-dotenv not installed — rely on real env vars

# Docker compose maps CLINIC_SIDECAR_JWT_SECRET → SIDECAR_JWT_SECRET.
# Native mode: do the same mapping if SIDECAR_JWT_SECRET is not already set.
# Fallback to "" so the sidecar never crashes with a KeyError when the key is unset.
if "SIDECAR_JWT_SECRET" not in os.environ:
    os.environ["SIDECAR_JWT_SECRET"] = os.environ.get("CLINIC_SIDECAR_JWT_SECRET", "")

from fastapi import FastAPI, Request
from starlette.middleware.base import BaseHTTPMiddleware

from app.routes import health, consult, rag, forecast, metrics, analyse as analyse_route
from app.routes import admin as admin_route
from app.routes import ops as ops_route
from app.routes import compliance as compliance_route
from app.routes.metrics import REQUEST_COUNT, REQUEST_DURATION


class PrometheusMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        start = time.perf_counter()
        response = await call_next(request)
        duration = time.perf_counter() - start
        endpoint = request.url.path
        REQUEST_COUNT.labels(
            method=request.method,
            endpoint=endpoint,
            status_code=str(response.status_code),
        ).inc()
        REQUEST_DURATION.labels(method=request.method, endpoint=endpoint).observe(duration)
        return response


async def _bootstrap_model_config() -> None:
    """
    On startup, read all model_config rows from platform_settings and apply them
    to os.environ. This makes the DB the single source of truth for the full
    pipeline — no manual .env edits needed after saving config in the UI.
    Fails silently so a missing DB connection never blocks sidecar startup.
    """
    from app.routes.health import _DB_TO_ENV
    try:
        from app.services import db as db_service
        async with db_service.cursor() as cur:
            await cur.execute(
                "SELECT platform_name, meta FROM platform_settings "
                "WHERE provider = 'model_config'"
            )
            rows = await cur.fetchall()
        import json as _json
        for row in rows:
            env_key = _DB_TO_ENV.get(row["platform_name"])
            if not env_key:
                continue
            try:
                meta_raw = row["meta"] if "meta" in row else (row.get("meta") or "{}")
                parsed = _json.loads(meta_raw) if isinstance(meta_raw, str) else meta_raw
                val = parsed.get("value", "") if isinstance(parsed, dict) else ""
            except Exception:
                val = ""
            if val:
                os.environ[env_key] = str(val)
        logger.info("bootstrap: loaded %d model_config rows from DB", len(rows))
    except Exception as exc:
        logger.warning("bootstrap: could not load model_config from DB (%s) — using env/defaults", exc)


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Load model provider config from DB so a UI save persists across sidecar restarts.
    await _bootstrap_model_config()
    # Pre-warm all four ETCSLV persona harnesses (not lazy at first request)
    from app.agent.harness_factory import HarnessFactory
    HarnessFactory.clinical()
    HarnessFactory.admin()
    HarnessFactory.ops()
    HarnessFactory.compliance()
    yield


app = FastAPI(
    title="Aviva Clinic AI Sidecar",
    version="4.0.0",  # ETCSLV multi-persona harness (Phase 8)
    lifespan=lifespan,
    docs_url="/docs",
    redoc_url=None,
)

app.add_middleware(PrometheusMiddleware)

app.include_router(health.router)
app.include_router(consult.router, prefix="/v1")
app.include_router(rag.router, prefix="/v1")
app.include_router(forecast.router, prefix="/v1")
app.include_router(admin_route.router, prefix="/v1")
app.include_router(ops_route.router, prefix="/v1")
app.include_router(compliance_route.router, prefix="/v1")
app.include_router(analyse_route.router, prefix="/v1")
app.include_router(metrics.router)  # GET /metrics — no auth, Prometheus scrapes this
