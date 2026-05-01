"""
Administrative tool factories — read-only DB queries via clinic_ro.

All tools fail-open: a DB outage degrades to an "insufficient data"
finding rather than a 500. The admin harness's verification gate then
caps confidence — owners are never silently misled.

Table mapping (correct as of 2026-04):
  revenue_anomaly  → invoices (created_at, department)
  discount_risk    → invoices (discount_amount, discount_status)
  fbr_status       → invoices (fbr_status column added in 2026_03_09 migration)
  payout_audit     → invoices (department='consultation', prescribing_doctor_id)
"""
from __future__ import annotations

import logging
from typing import Any

from app.agent.tool_registry import Tool
from app.services import db

logger = logging.getLogger(__name__)


def _make_failopen_tool(
    name: str,
    description: str,
    fn,
) -> Tool:
    async def _invoke(_ctx: dict) -> dict:
        try:
            return await fn()
        except Exception as exc:
            logger.warning("admin_tool[%s] failed (%s)", name, exc)
            return {
                "tool": name,
                "answer": f"Tool {name} unavailable: insufficient data.",
                "error_label": str(exc),
            }
    return Tool(
        name=name, description=description,
        schema={"type": "object", "properties": {}}, invoke_fn=_invoke,
        fail_open=True,
    )


def make_revenue_anomaly_tool(period_days: int) -> Tool:
    async def _query() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c "
                "FROM invoices "
                "WHERE created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY DATE(created_at) ORDER BY d",
                (period_days,),
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "revenue_anomaly", "answer": "No invoices found in window."}
        counts = [r["c"] for r in rows]
        avg = sum(counts) / len(counts)
        last = counts[-1] if counts else 0
        delta_pct = ((last - avg) / avg * 100) if avg else 0
        verdict = "anomaly" if abs(delta_pct) >= 30 else "normal"
        total = sum(counts)
        return {
            "tool": "revenue_anomaly",
            "answer": (
                f"Invoice activity last {period_days}d: {total} total, avg={avg:.1f}/day, "
                f"latest day={last}, Δ={delta_pct:+.1f}% → {verdict}."
            ),
        }
    return _make_failopen_tool(
        "revenue_anomaly",
        "Compares latest day's invoice volume to the period average.",
        _query,
    )


def make_discount_risk_tool(period_days: int) -> Tool:
    async def _query() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT discount_status, COUNT(*) AS c "
                "FROM invoices "
                "WHERE discount_amount > 0 "
                "AND created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY discount_status ORDER BY c DESC",
                (period_days,),
            )
            status_rows = await cur.fetchall()
            await cur.execute(
                "SELECT COUNT(*) AS total FROM invoices "
                "WHERE created_at >= NOW() - INTERVAL %s DAY",
                (period_days,),
            )
            total_row = await cur.fetchone()
        total_invoices = (total_row or {}).get("total", 0)
        if not status_rows:
            return {
                "tool": "discount_risk",
                "answer": f"No discounted invoices in last {period_days}d (total invoices: {total_invoices}).",
            }
        discounted = sum(r["c"] for r in status_rows)
        rate = (discounted / total_invoices * 100) if total_invoices else 0
        breakdown = ", ".join(f"{r['discount_status'] or 'unset'}={r['c']}" for r in status_rows)
        flagged = "elevated" if rate >= 20 else "normal"
        return {
            "tool": "discount_risk",
            "answer": (
                f"Discount activity last {period_days}d: {discounted}/{total_invoices} invoices "
                f"({rate:.1f}%) discounted → {flagged}. Breakdown: {breakdown}."
            ),
        }
    return _make_failopen_tool(
        "discount_risk",
        "Reports discount rate and status breakdown from invoices.",
        _query,
    )


def make_fbr_status_tool(period_days: int) -> Tool:
    async def _query() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT COALESCE(fbr_status, 'not_submitted') AS fbr_status, COUNT(*) AS c "
                "FROM invoices "
                "WHERE created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY fbr_status ORDER BY c DESC",
                (period_days,),
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "fbr_status", "answer": "No invoices in window for FBR analysis."}
        submitted = sum(r["c"] for r in rows if (r["fbr_status"] or "").lower() == "submitted")
        failed = sum(r["c"] for r in rows if (r["fbr_status"] or "").lower() in ("failed", "error"))
        pending = sum(r["c"] for r in rows if (r["fbr_status"] or "").lower() in ("not_submitted", ""))
        return {
            "tool": "fbr_status",
            "answer": (
                f"FBR status last {period_days}d: submitted={submitted}, "
                f"failed={failed}, not_submitted={pending}."
            ),
        }
    return _make_failopen_tool(
        "fbr_status",
        "Reports FBR submission breakdown from invoices.fbr_status column.",
        _query,
    )


def make_payout_audit_tool(period_days: int) -> Tool:
    async def _query() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT prescribing_doctor_id AS user_id, COUNT(*) AS consultations "
                "FROM invoices "
                "WHERE department = 'consultation' "
                "AND created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY prescribing_doctor_id ORDER BY consultations DESC LIMIT 10",
                (period_days,),
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "payout_audit", "answer": "No consultation invoices in window."}
        summary = ", ".join(
            f"u{r['user_id']}={r['consultations']}"
            for r in rows[:5]
            if r.get("user_id") is not None
        )
        total = sum(r["consultations"] for r in rows)
        return {
            "tool": "payout_audit",
            "answer": (
                f"Consultation invoices last {period_days}d: {total} total. "
                f"Top doctors: {summary}."
            ),
        }
    return _make_failopen_tool(
        "payout_audit",
        "Counts consultation invoices per doctor for payout cross-check.",
        _query,
    )
