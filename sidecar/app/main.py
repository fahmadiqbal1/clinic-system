import time
from contextlib import asynccontextmanager

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


@asynccontextmanager
async def lifespan(app: FastAPI):
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
