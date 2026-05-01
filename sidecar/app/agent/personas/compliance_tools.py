"""
Compliance tool factories — deterministic checks that feed the model.

These are NOT advisory: a failed audit chain or PHI access anomaly is a
hard fact. The compliance harness verification gate enforces that the
model cites the specific evidence rows the tools returned.
"""
from __future__ import annotations

import logging
import os
import subprocess
from pathlib import Path

from app.agent.tool_registry import Tool
from app.services import db

logger = logging.getLogger(__name__)


def _failopen(name: str, description: str, fn) -> Tool:
    async def _invoke(_ctx: dict) -> dict:
        try:
            return await fn()
        except Exception as exc:
            logger.warning("compliance_tool[%s] failed (%s)", name, exc)
            return {"tool": name, "answer": f"Tool {name} unavailable.", "error_label": str(exc)}
    return Tool(
        name=name, description=description,
        schema={"type": "object", "properties": {}}, invoke_fn=_invoke,
        fail_open=True,
    )


def _project_root() -> Path:
    return Path(__file__).resolve().parents[4]


def make_audit_chain_tool() -> Tool:
    """
    Runs `php artisan audit:verify-chain --chunk=500` and reports pass/fail.
    Hard signal — if this fails, status MUST be NON_COMPLIANT.
    """
    async def _q() -> dict:
        try:
            result = subprocess.run(
                ["php", "artisan", "audit:verify-chain", "--chunk=500"],
                cwd=str(_project_root()),
                capture_output=True, text=True, timeout=60,
            )
            output = (result.stdout + "\n" + result.stderr).strip()
            ok = result.returncode == 0
            tail = output[-400:]
            return {
                "tool": "audit_chain_verify",
                "answer": (
                    f"audit:verify-chain exit={result.returncode} "
                    f"({'PASS' if ok else 'FAIL'})\n{tail}"
                ),
                "exit_code": result.returncode,
                "compliant": ok,
            }
        except FileNotFoundError:
            return {"tool": "audit_chain_verify", "answer": "php binary not found from sidecar."}
        except subprocess.TimeoutExpired:
            return {"tool": "audit_chain_verify", "answer": "audit:verify-chain timed out (>60s)."}
    return _failopen(
        "audit_chain_verify",
        "Runs the audit_logs hash-chain verifier. Pass = chain intact.",
        _q,
    )


def make_phi_access_scan_tool(period_days: int) -> Tool:
    async def _q() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT user_id, COUNT(*) AS c FROM audit_logs "
                "WHERE auditable_type = 'App\\\\Models\\\\Patient' "
                "AND created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY user_id, DATE(created_at), HOUR(created_at) "
                "HAVING c > 20 ORDER BY c DESC LIMIT 10",
                (period_days,),
            )
            rows = await cur.fetchall()
        if not rows:
            return {
                "tool": "phi_access_scan",
                "answer": "No PHI access anomalies (>20 records/user/hour) in window.",
                "anomalies": 0,
            }
        summary = ", ".join(f"u{r['user_id']}={r['c']}" for r in rows[:5])
        return {
            "tool": "phi_access_scan",
            "answer": f"PHI access anomalies (>20 records/user/hour): {summary}",
            "anomalies": len(rows),
        }
    return _failopen(
        "phi_access_scan",
        "Scans audit_logs for unusual patient-record access concentration.",
        _q,
    )


def make_flag_snapshot_tool() -> Tool:
    async def _q() -> dict:
        import json as _json
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT platform_name, meta FROM platform_settings "
                "WHERE provider = 'feature_flag' ORDER BY platform_name"
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "flag_snapshot", "answer": "No feature flags found."}
        on: list[str] = []
        off: list[str] = []
        for r in rows:
            try:
                meta = _json.loads(r["meta"]) if isinstance(r["meta"], str) else (r["meta"] or {})
                enabled = bool(meta.get("value", False)) if isinstance(meta, dict) else False
            except Exception:
                enabled = False
            (on if enabled else off).append(r["platform_name"])
        return {
            "tool": "flag_snapshot",
            "answer": (
                f"Feature flags ON ({len(on)}): {', '.join(on[:8])}{'…' if len(on) > 8 else ''}; "
                f"OFF ({len(off)})."
            ),
        }
    return _failopen(
        "flag_snapshot",
        "Reads current feature flag states from platform_settings.",
        _q,
    )


def make_evidence_gap_tool() -> Tool:
    async def _q() -> dict:
        soc2_dir = _project_root() / "storage" / "app" / "soc2"
        if not soc2_dir.exists():
            return {"tool": "evidence_gap", "answer": "No SOC2 evidence directory yet."}
        zips = sorted(soc2_dir.glob("*.zip"), key=lambda p: p.stat().st_mtime, reverse=True)
        if not zips:
            return {"tool": "evidence_gap", "answer": "No SOC2 evidence ZIPs present — gap = full period."}
        latest = zips[0]
        import time as _t
        age_days = (_t.time() - latest.stat().st_mtime) / 86400.0
        verdict = "stale" if age_days > 30 else "current"
        return {
            "tool": "evidence_gap",
            "answer": (
                f"Latest SOC2 evidence: {latest.name} "
                f"(age={age_days:.1f}d, status={verdict})."
            ),
        }
    return _failopen(
        "evidence_gap",
        "Reports staleness of the latest SOC2 evidence export.",
        _q,
    )
