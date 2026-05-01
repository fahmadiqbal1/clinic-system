"""
Read-only MySQL connection pool for AI tool queries.

Credential resolution order (first match wins):
  1. CLINIC_RO_* vars — dedicated read-only user (preferred for production)
  2. DB_* vars        — standard Laravel vars (used in native dev when clinic_ro is not configured)

Host resolution: CLINIC_RO_HOST → DB_HOST → 127.0.0.1
  (avoids host.docker.internal which does not resolve in native XAMPP mode)
"""
from __future__ import annotations

import os
from contextlib import asynccontextmanager
from typing import AsyncIterator

import aiomysql

_pool: aiomysql.Pool | None = None


def _resolve_credentials() -> dict | None:
    """Return connection kwargs or None if not enough config is available."""
    # Prefer dedicated read-only vars; fall back to standard Laravel DB vars.
    # NOTE: password may legitimately be an empty string (XAMPP default root).
    #       Use `is None` checks, not truthiness, so empty-string passwords work.
    ro_pass = os.environ.get("CLINIC_RO_PASSWORD")
    db_pass = os.environ.get("DB_PASSWORD")
    password = ro_pass if ro_pass is not None else db_pass

    database = os.environ.get("CLINIC_RO_DATABASE") or os.environ.get("DB_DATABASE")

    # database must be non-empty; password can be empty string (no-password MySQL)
    if password is None or not database:
        return None

    return {
        "host": os.environ.get("CLINIC_RO_HOST") or os.environ.get("DB_HOST", "127.0.0.1"),
        "port": int(os.environ.get("CLINIC_RO_PORT") or os.environ.get("DB_PORT", "3306")),
        "user": os.environ.get("CLINIC_RO_USER") or os.environ.get("DB_USERNAME", "root"),
        "password": password,
        "db": database,
    }


async def _ensure_pool() -> aiomysql.Pool | None:
    global _pool
    if _pool is not None:
        return _pool
    creds = _resolve_credentials()
    if creds is None:
        return None
    _pool = await aiomysql.create_pool(
        **creds,
        minsize=1,
        maxsize=3,
        autocommit=True,
    )
    return _pool


@asynccontextmanager
async def cursor() -> AsyncIterator[aiomysql.cursors.DictCursor]:
    pool = await _ensure_pool()
    if pool is None:
        raise RuntimeError(
            "DB not configured — set DB_DATABASE + DB_PASSWORD (or CLINIC_RO_DATABASE + CLINIC_RO_PASSWORD) in .env"
        )
    async with pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            yield cur
