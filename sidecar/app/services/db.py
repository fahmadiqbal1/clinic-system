"""
Read-only MySQL connection pool for the clinic_ro user.
Used exclusively for trend/forecast queries. Degrades gracefully if not configured.
"""
from __future__ import annotations

import os
from contextlib import asynccontextmanager
from typing import AsyncIterator

import aiomysql

_pool: aiomysql.Pool | None = None


def _is_configured() -> bool:
    return bool(os.environ.get("CLINIC_RO_PASSWORD") and os.environ.get("CLINIC_RO_DATABASE"))


async def _ensure_pool() -> aiomysql.Pool | None:
    global _pool
    if _pool is not None:
        return _pool
    if not _is_configured():
        return None
    _pool = await aiomysql.create_pool(
        host=os.environ.get("CLINIC_RO_HOST", "host.docker.internal"),
        port=int(os.environ.get("CLINIC_RO_PORT", "3306")),
        user=os.environ.get("CLINIC_RO_USER", "clinic_ro"),
        password=os.environ["CLINIC_RO_PASSWORD"],
        db=os.environ["CLINIC_RO_DATABASE"],
        minsize=1,
        maxsize=3,
        autocommit=True,
    )
    return _pool


@asynccontextmanager
async def cursor() -> AsyncIterator[aiomysql.cursors.DictCursor]:
    pool = await _ensure_pool()
    if pool is None:
        raise RuntimeError("clinic_ro DB not configured")
    async with pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            yield cur
