"""
RAG endpoints — stub for Phase 2. RAGFlow integration arrives in Phase 3.
Schema contracts are final; implementation swaps in behind them.
"""

import hashlib
import os

from fastapi import APIRouter, Depends
from fastapi.security import HTTPAuthorizationCredentials

from app.auth import security, verify_jwt
from app.schemas.medical import RagIngestInput, RagIngestOutput, RagQueryInput, RagQueryOutput

router = APIRouter()


@router.post("/rag/query", response_model=RagQueryOutput)
async def rag_query(
    body: RagQueryInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> RagQueryOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    return RagQueryOutput(
        answer="RAGFlow not yet configured — enable in Phase 3.",
        citations=[],
        model_id="stub",
    )


@router.post("/rag/ingest", response_model=RagIngestOutput)
async def rag_ingest(
    body: RagIngestInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> RagIngestOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])
    ingestion_id = hashlib.sha256(body.filePath.encode()).hexdigest()[:16]
    return RagIngestOutput(ingestion_id=ingestion_id, status="queued")
