"""
RAG endpoints — wired to RAGFlow in Phase 3.
Falls back gracefully when RAGFLOW_URL is not configured or RAGFlow is unreachable.
"""
from __future__ import annotations

import hashlib
import os
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.auth import security, verify_jwt
from app.schemas.medical import RagIngestInput, RagIngestOutput, RagQueryInput, RagQueryOutput
from app.services import ragflow

router = APIRouter()


@router.post("/rag/query", response_model=RagQueryOutput)
async def rag_query(
    body: RagQueryInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> RagQueryOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    try:
        result = await ragflow.query(body.query, body.collection)
        return RagQueryOutput(
            answer=result["answer"],
            citations=result["citations"],
            model_id="ragflow",
        )
    except RuntimeError:
        # RAGFlow not configured — degrade to empty answer.
        return RagQueryOutput(
            answer="Knowledge assistant not available — RAGFlow not configured.",
            citations=[],
            model_id="stub",
        )
    except Exception as exc:
        raise HTTPException(status_code=503, detail=f"RAGFlow unavailable: {exc}") from exc


@router.post("/rag/ingest", response_model=RagIngestOutput)
async def rag_ingest(
    body: RagIngestInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> RagIngestOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    if not body.filePath and not body.content:
        raise HTTPException(status_code=422, detail="Provide either filePath or content.")

    try:
        if body.content:
            ingestion_id = await ragflow.ingest_text(body.content, body.collection)
        else:
            ingestion_id = await ragflow.ingest_file(body.filePath, body.collection)
        return RagIngestOutput(ingestion_id=ingestion_id, status="queued")
    except RuntimeError:
        # RAGFlow not configured — return a deterministic stub ID.
        fallback_id = hashlib.sha256((body.filePath or body.content or "").encode()).hexdigest()[:16]
        return RagIngestOutput(ingestion_id=fallback_id, status="skipped_not_configured")
    except Exception as exc:
        raise HTTPException(status_code=503, detail=f"RAGFlow ingest failed: {exc}") from exc
