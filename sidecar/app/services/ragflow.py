"""
RAGFlow HTTP client — wraps RAGFlow REST API for query and ingest.
Fails closed (raises) so callers can degrade gracefully.
Skips silently when RAGFLOW_URL is not configured.
"""
from __future__ import annotations

import os
import tempfile
from pathlib import Path
from typing import Any

import httpx

RAGFLOW_URL = os.environ.get("RAGFLOW_URL", "").rstrip("/")
RAGFLOW_KEY = os.environ.get("RAGFLOW_API_KEY", "")
# Default dataset IDs — Owner configures these in RAGFlow UI after first boot.
DATASET_IDS: dict[str, str] = {
    "general":        os.environ.get("RAGFLOW_DATASET_GENERAL",  "general"),
    "service_catalog": os.environ.get("RAGFLOW_DATASET_CATALOG",  "service_catalog"),
    "inventory":       os.environ.get("RAGFLOW_DATASET_INVENTORY", "inventory"),
}


def _is_configured() -> bool:
    return bool(RAGFLOW_URL and RAGFLOW_KEY)


def _headers() -> dict[str, str]:
    return {"Authorization": f"Bearer {RAGFLOW_KEY}"}


async def query(question: str, collection: str = "general") -> dict[str, Any]:
    """Run a RAG retrieval query. Returns answer + citations. Raises on error."""
    if not _is_configured():
        raise RuntimeError("RAGFlow not configured (RAGFLOW_URL/RAGFLOW_API_KEY missing).")

    dataset_id = DATASET_IDS.get(collection, collection)
    async with httpx.AsyncClient(base_url=RAGFLOW_URL, headers=_headers(), timeout=30.0) as c:
        r = await c.post("/api/v1/retrieval", json={
            "question": question,
            "dataset_ids": [dataset_id],
            "page_size": 5,
        })
        r.raise_for_status()
        data = r.json().get("data", {})
        chunks = data.get("chunks", [])
        # Build human-readable citations from chunk metadata.
        citations = []
        for ch in chunks[:5]:
            doc = ch.get("document_keyword") or ch.get("docnm_kwd", "doc")
            positions = ch.get("positions", [[0]])
            page = positions[0][0] if positions else 0
            citations.append(f"{doc} p.{page}" if page else doc)
        return {"answer": data.get("answer", ""), "citations": citations}


async def ingest_file(file_path: str, collection: str = "general") -> str:
    """Upload a file to RAGFlow. Returns the ingestion document ID."""
    if not _is_configured():
        raise RuntimeError("RAGFlow not configured (RAGFLOW_URL/RAGFLOW_API_KEY missing).")

    dataset_id = DATASET_IDS.get(collection, collection)
    path = Path(file_path)
    async with httpx.AsyncClient(base_url=RAGFLOW_URL, headers=_headers(), timeout=60.0) as c:
        with path.open("rb") as fh:
            r = await c.post(
                f"/api/v1/dataset/{dataset_id}/document",
                files={"file": (path.name, fh, "application/octet-stream")},
            )
        r.raise_for_status()
        return r.json().get("data", {}).get("id", file_path)


async def ingest_text(content: str, collection: str = "general") -> str:
    """Write content to a temp file and upload it to RAGFlow."""
    if not _is_configured():
        raise RuntimeError("RAGFlow not configured (RAGFLOW_URL/RAGFLOW_API_KEY missing).")

    with tempfile.NamedTemporaryFile(
        mode="w", suffix=".txt", delete=False, prefix=f"ragflow_{collection}_"
    ) as tf:
        tf.write(content)
        tmp_path = tf.name

    try:
        return await ingest_file(tmp_path, collection)
    finally:
        Path(tmp_path).unlink(missing_ok=True)
