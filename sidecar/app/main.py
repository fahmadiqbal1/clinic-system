from contextlib import asynccontextmanager
from fastapi import FastAPI

from app.routes import health, consult, rag, forecast


@asynccontextmanager
async def lifespan(app: FastAPI):
    yield


app = FastAPI(
    title="Aviva Clinic AI Sidecar",
    version="2.0.0",
    lifespan=lifespan,
    docs_url="/docs",
    redoc_url=None,
)

app.include_router(health.router)
app.include_router(consult.router, prefix="/v1")
app.include_router(rag.router, prefix="/v1")
app.include_router(forecast.router, prefix="/v1")
