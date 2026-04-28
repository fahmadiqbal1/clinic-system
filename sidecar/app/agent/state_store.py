"""
S — State Store

Per-case-token state persistence across consultations.
Prevents context ROT by carrying forward:
  - Prior consultation summary (first 500 chars — enough to orient the model)
  - Confidence trend (last 5 scores)
  - Consultation count (for longitudinal awareness)
  - Escalation flags

Backend: in-memory with TTL eviction.
Production upgrade path: set REDIS_URL env var → swap to aioredis backend
without changing the interface.
"""
from __future__ import annotations

import asyncio
import logging
import time
from dataclasses import dataclass, field

logger = logging.getLogger(__name__)

STATE_TTL_SECONDS = 86400  # 24 hours — matches clinical session cadence


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
            "last_summary": self.last_summary,
            "confidence_history": self.confidence_history,
            "consultation_count": self.consultation_count,
            "escalation_pending": self.escalation_pending,
        }


class StateStore:
    """
    In-memory state store with TTL eviction — the S pillar.

    Thread-safety: asyncio.Lock guards all mutations.
    Eviction: lazy (on get/put) + periodic via evict_expired().
    """

    def __init__(self) -> None:
        self._store: dict[str, CaseState] = {}
        self._lock = asyncio.Lock()

    async def get(self, case_token: str) -> dict | None:
        async with self._lock:
            state = self._store.get(case_token)
            if state is None or state.is_expired():
                if state is not None:
                    del self._store[case_token]
                    logger.debug("StateStore: evicted expired state for %s…", case_token[:8])
                return None
            return state.to_dict()

    async def put(self, case_token: str, result: dict) -> None:
        async with self._lock:
            state = self._store.get(case_token)
            if state is None or state.is_expired():
                state = CaseState(case_token=case_token)

            if result.get("rationale"):
                state.last_summary = result["rationale"][:500]
            if result.get("confidence") is not None:
                state.confidence_history.append(float(result["confidence"]))
                state.confidence_history = state.confidence_history[-5:]
            state.consultation_count += 1
            state.last_updated = time.time()
            self._store[case_token] = state
            logger.debug(
                "StateStore: updated case %s… (count=%d)", case_token[:8], state.consultation_count
            )

    async def evict_expired(self) -> int:
        """Evict all expired entries. Returns eviction count."""
        async with self._lock:
            expired = [k for k, v in self._store.items() if v.is_expired()]
            for k in expired:
                del self._store[k]
            if expired:
                logger.info("StateStore: evicted %d expired states", len(expired))
            return len(expired)

    @property
    def size(self) -> int:
        return len(self._store)
