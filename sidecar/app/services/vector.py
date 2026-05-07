"""
In-memory cosine similarity vector store — Phase 9A.

Stores embedding vectors as plain Python lists alongside text and metadata.
At query time, loads all stored vectors and ranks them by cosine similarity
using numpy — no external vector DB required.

Intended use: cache a working set of embeddings (e.g. today's service catalog
or consultation summaries) in the sidecar process. For larger corpora, the
store should be rebuilt on startup from a DB or file source.
"""
from __future__ import annotations

from typing import Any

import numpy as np


def cosine_similarity(a: list[float], b: list[float]) -> float:
    va = np.array(a, dtype=np.float32)
    vb = np.array(b, dtype=np.float32)
    norm_a = float(np.linalg.norm(va))
    norm_b = float(np.linalg.norm(vb))
    if norm_a == 0.0 or norm_b == 0.0:
        return 0.0
    return float(np.dot(va, vb) / (norm_a * norm_b))


class EmbeddingStore:
    def __init__(self) -> None:
        self._items: list[dict[str, Any]] = []

    def add(
        self,
        id: str,
        text: str,
        embedding: list[float],
        metadata: dict | None = None,
    ) -> None:
        # Replace existing entry with same id
        self._items = [it for it in self._items if it["id"] != id]
        self._items.append(
            {
                "id":        id,
                "text":      text,
                "embedding": embedding,
                "metadata":  metadata or {},
            }
        )

    def search(
        self,
        query_embedding: list[float],
        top_k: int = 5,
    ) -> list[dict[str, Any]]:
        if not self._items:
            return []

        scored = [
            {
                "id":       it["id"],
                "text":     it["text"],
                "score":    cosine_similarity(query_embedding, it["embedding"]),
                "metadata": it["metadata"],
            }
            for it in self._items
        ]
        scored.sort(key=lambda x: x["score"], reverse=True)
        return scored[:top_k]

    def size(self) -> int:
        return len(self._items)

    def clear(self) -> None:
        self._items = []


# Module-level singleton — shared across the sidecar process lifetime
_store: EmbeddingStore = EmbeddingStore()


def get_store() -> EmbeddingStore:
    return _store
