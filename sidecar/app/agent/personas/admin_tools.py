"""
Administrative tool factories — read-only DB queries via clinic_ro.

All tools fail-open: a DB outage degrades to an "insufficient data"
finding rather than a 500. The admin harness's verification gate then
caps confidence — owners are never silently misled.
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
                "FROM audit_logs "
                "WHERE action LIKE 'invoice.%' "
                "AND created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY DATE(created_at) ORDER BY d",
                (period_days,),
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "revenue_anomaly", "answer": "No invoice events in window."}
        counts = [r["c"] for r in rows]
        avg = sum(counts) / len(counts)
        last = counts[-1] if counts else 0
        delta_pct = ((last - avg) / avg * 100) if avg else 0
        verdict = "anomaly" if abs(delta_pct) >= 30 else "normal"
        return {
            "tool": "revenue_anomaly",
            "answer": (
                f"Revenue events last {period_days}d: avg={avg:.1f}/day, "
                f"latest={last}, Δ={delta_pct:+.1f}% → {verdict}."
            ),
        }
    return _make_failopen_tool(
        "revenue_anomaly",
        "Compares latest day's revenue events to the period average.",
        _query,
    )


def make_discount_risk_tool(period_days: int) -> Tool:
    async def _query() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT user_id, COUNT(*) AS c "
                "FROM audit_logs "
                "WHERE action = 'invoice.discount.requested' "
                "AND created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY user_id "
                "ORDER BY c DESC LIMIT 5",
                (period_days,),
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "discount_risk", "answer": "No discount requests in window."}
        top = rows[0]
        flagged = "elevated" if top["c"] >= 10 else "normal"
        return {
            "tool": "discount_risk",
            "answer": (
                f"Top requester user_id={top['user_id']} with {top['c']} discount "
                f"requests in {period_days}d → {flagged} concentration."
            ),
        }
    return _make_failopen_tool(
        "discount_risk",
        "Identifies discount-request concentration by staff member.",
        _query,
    )


def make_fbr_status_tool(period_days: int) -> Tool:
    async def _query() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT action, COUNT(*) AS c "
                "FROM audit_logs "
                "WHERE action LIKE 'fbr.%' "
                "AND created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY action",
                (period_days,),
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "fbr_status", "answer": "No FBR events in window."}
        succ = sum(r["c"] for r in rows if "success" in r["action"])
        fail = sum(r["c"] for r in rows if "fail" in r["action"] or "error" in r["action"])
        return {
            "tool": "fbr_status",
            "answer": f"FBR events last {period_days}d: success={succ}, failures={fail}.",
        }
    return _make_failopen_tool(
        "fbr_status",
        "Reports recent FBR submission successes vs failures.",
        _query,
    )


def make_payout_audit_tool(period_days: int) -> Tool:
    async def _query() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT user_id, COUNT(*) AS consultations "
                "FROM audit_logs "
                "WHERE action = 'consultation.completed' "
                "AND created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY user_id ORDER BY consultations DESC LIMIT 10",
                (period_days,),
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "payout_audit", "answer": "No consultation events in window."}
        summary = ", ".join(f"u{r['user_id']}={r['consultations']}" for r in rows[:5])
        return {
            "tool": "payout_audit",
            "answer": f"Top staff consultation counts last {period_days}d: {summary}.",
        }
    return _make_failopen_tool(
        "payout_audit",
        "Aggregates per-staff consultation counts for payout cross-check.",
        _query,
    )
