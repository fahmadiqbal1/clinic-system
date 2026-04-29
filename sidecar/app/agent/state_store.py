"""
S — State Store

Per-case-token state persistence across consultations.
Prevents context ROT by carrying forward:
  - Prior consultation summary (first 500 chars — enough to orient the model)
  - Confidence trend (last 5 scores)
  - Consultation count (for longitudinal awareness)
  - Escalation flags

Backend selection (auto):
  - If REDIS_URL env var is set: redis.asyncio backend (survives restarts)
  - Otherwise: in-memory dict (dev / single-process)

The interface is identical across backends. Each StateStore instance has
a `namespace` so the four ETCSLV harnesses (clinical / admin / ops /
compliance) can share a single Redis without key collisions.
"""
from __future__ import annotations

import asyncio
import json
import logging
import os
import time
from dataclasses import dataclass, field
from typing import Any

logger = logging.getLogger(__name__)

STATE_TTL_SECONDS = 86400  # 24 hours — matches clinical session cadence


# ── Optional Redis import (failure is non-fatal — fall back to in-memory) ──
try:
    import redis.asyncio as aioredis  # type: ignore
    _REDIS_AVAILABLE = True
except Exception:  # pragma: no cover — only hit when redis package missing
    aioredis = None  # type: ignore
    _REDIS_AVAILABLE = False


@dataclass
class CaseState:
    case_token: str
    last_summary: str = ""
    confidence_history: list[float] = field(default_factory=list)
    consultation_count: int = 0
    last_updated: float = field(default_factory=time.time)
    escalation_pending: bool = False

    def is_expired(self) -> bool:
        return (time.time() - self.last_updated) > STATE_TTL_SECONDS

    def to_dict(self) -> dict:
        return {
            "case_token": self.case_token,
            "last_summary": self.last_summary,
            "confidence_history": self.confidence_history,
            "consultation_count": self.consultation_count,
            "last_updated": self.last_updated,
            "escalation_pending": self.escalation_pending,
        }

    def to_public_dict(self) -> dict:
        """Subset returned to callers (used by ContextManager)."""
        return {
            "last_summary": self.last_summary,
            "confidence_history": self.confidence_history,
            "consultation_count": self.consultation_count,
            "escalation_pending": self.escalation_pending,
        }

    @classmethod
    def from_dict(cls, data: dict) -> "CaseState":
        return cls(
            case_token=data.get("case_token", ""),
            last_summary=data.get("last_summary", ""),
            confidence_history=list(data.get("confidence_history", [])),
            consultation_count=int(data.get("consultation_count", 0)),
            last_updated=float(data.get("last_updated", time.time())),
            escalation_pending=bool(data.get("escalation_pending", False)),
        )


class StateStore:
    """
    Namespaced state store with TTL — the S pillar.

    Use namespace="clinical" / "admin" / "ops" / "compliance" to keep
    per-agent state isolated when sharing a single Redis.
    """

    def __init__(
        self,
        namespace: str = "clinical",
        redis_url: str | None = None,
    ) -> None:
        self.namespace = namespace
        self._url = redis_url if redis_url is not None else os.environ.get("REDIS_URL")
        self._redis: Any | None = None
        self._store: dict[str, CaseState] = {}
        self._lock = asyncio.Lock()

        if self._url and _REDIS_AVAILABLE:
            try:
                self._redis = aioredis.from_url(  # type: ignore[union-attr]
                    self._url, encoding="utf-8", decode_responses=True
                )
                logger.info(
                    "StateStore[%s]: Redis backend at %s", namespace, self._url
                )
            except Exception as exc:  # pragma: no cover
                logger.error(
                    "StateStore[%s]: Redis init failed (%s); falling back to in-memory",
                    namespace,
                    exc,
                )
                self._redis = None
        else:
            logger.info("StateStore[%s]: in-memory backend", namespace)

    # ── Key helpers ────────────────────────────────────────────────────
    def _key(self, case_token: str) -> str:
        return f"etcslv:{self.namespace}:{case_token}"

    @property
    def is_redis(self) -> bool:
        return self._redis is not None

    # ── Public API ─────────────────────────────────────────────────────
    async def get(self, case_token: str) -> dict | None:
        if self._redis is not None:
            try:
                raw = await self._redis.get(self._key(case_token))
            except Exception as exc:
                logger.warning(
                    "StateStore[%s]: Redis get failed (%s) — using in-memory copy",
                    self.namespace,
                    exc,
                )
                raw = None
            if raw:
                try:
                    state = CaseState.from_dict(json.loads(raw))
                    if not state.is_expired():
                        return state.to_public_dict()
                except (json.JSONDecodeError, ValueError):
                    pass
            return None

        async with self._lock:
            state = self._store.get(case_token)
            if state is None or state.is_expired():
                if state is not None:
                    del self._store[case_token]
                    logger.debug(
                        "StateStore[%s]: evicted expired state for %s…",
                        self.namespace,
                        case_token[:8],
                    )
                return None
            return state.to_public_dict()

    async def put(self, case_token: str, result: dict) -> None:
        # Build/update the canonical CaseState first
        existing = await self._get_full(case_token)
        state = existing or CaseState(case_token=case_token)
        if existing is not None and existing.is_expired():
            state = CaseState(case_token=case_token)

        if result.get("rationale"):
            state.last_summary = result["rationale"][:500]
        if result.get("confidence") is not None:
            state.confidence_history.append(float(result["confidence"]))
            state.confidence_history = state.confidence_history[-5:]
        if result.get("escalation_pending") is not None:
            state.escalation_pending = bool(result["escalation_pending"])
        state.consultation_count += 1
        state.last_updated = time.time()

        if self._redis is not None:
            try:
                await self._redis.set(
                    self._key(case_token),
                    json.dumps(state.to_dict()),
                    ex=STATE_TTL_SECONDS,
                )
            except Exception as exc:
                logger.warning(
                    "StateStore[%s]: Redis put failed (%s) — keeping in-memory copy",
                    self.namespace,
                    exc,
                )
                async with self._lock:
                    self._store[case_token] = state
        else:
            async with self._lock:
                self._store[case_token] = state

        logger.debug(
            "StateStore[%s]: updated case %s… (count=%d)",
            self.namespace,
            case_token[:8],
            state.consultation_count,
        )

    async def _get_full(self, case_token: str) -> CaseState | None:
        """Internal: returns the full CaseState (used by put to merge)."""
        if self._redis is not None:
            try:
                raw = await self._redis.get(self._key(case_token))
                if raw:
                    return CaseState.from_dict(json.loads(raw))
            except Exception:
                return None
            return None
        async with self._lock:
            return self._store.get(case_token)

    async def evict_expired(self) -> int:
        """In-memory only: evict all expired entries (Redis uses TTL natively)."""
        if self._redis is not None:
            return 0
        async with self._lock:
            expired = [k for k, v in self._store.items() if v.is_expired()]
            for k in expired:
                del self._store[k]
            if expired:
                logger.info(
                    "StateStore[%s]: evicted %d expired states",
                    self.namespace,
                    len(expired),
                )
            return len(expired)

    @property
    def size(self) -> int:
        return len(self._store)
