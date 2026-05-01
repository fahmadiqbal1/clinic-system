"""
Operations tool factories — inventory velocity, procurement, expense, queue.

All tools fail-open. The ops harness verification gate forces an explicit
"data unavailable" mention when tool output is empty.
"""
from __future__ import annotations

import logging
import os
import subprocess
from typing import Any

from app.agent.tool_registry import Tool
from app.services import db

logger = logging.getLogger(__name__)


def _failopen(name: str, description: str, fn) -> Tool:
    async def _invoke(_ctx: dict) -> dict:
        try:
            return await fn()
        except Exception as exc:
            logger.warning("ops_tool[%s] failed (%s)", name, exc)
            return {"tool": name, "answer": f"Tool {name} unavailable.", "error_label": str(exc)}
    return Tool(
        name=name, description=description,
        schema={"type": "object", "properties": {}}, invoke_fn=_invoke,
        fail_open=True,
    )


def make_inventory_velocity_tool() -> Tool:
    async def _q() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT id, name, quantity_in_stock AS qty, "
                "minimum_stock_level AS min_lvl, "
                "GREATEST(minimum_stock_level * 3, minimum_stock_level + 50) AS max_lvl "
                "FROM inventory_items WHERE is_active = 1 ORDER BY name"
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "inventory_velocity", "answer": "No active inventory items."}
        critical = [r for r in rows if (r.get("qty") or 0) == 0]
        warning = [
            r for r in rows
            if (r.get("qty") or 0) > 0
            and (r.get("min_lvl") is not None)
            and (r.get("qty") or 0) <= (r.get("min_lvl") or 0)
        ]
        bullets = []
        if critical:
            bullets.append(f"CRITICAL stock-out items: {len(critical)} (e.g. " +
                           ", ".join(r["name"] for r in critical[:3]) + ")")
        if warning:
            bullets.append(f"WARNING below-minimum items: {len(warning)} (e.g. " +
                           ", ".join(r["name"] for r in warning[:3]) + ")")
        if not bullets:
            bullets.append("All active items above minimum stock levels.")
        return {"tool": "inventory_velocity", "answer": "\n".join(bullets)}
    return _failopen(
        "inventory_velocity",
        "Classifies active inventory items as critical / warning / ok.",
        _q,
    )


def make_procurement_recommendation_tool() -> Tool:
    async def _q() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT name, quantity_in_stock AS qty, minimum_stock_level AS min_lvl, "
                "GREATEST(minimum_stock_level * 3, minimum_stock_level + 50) AS max_lvl "
                "FROM inventory_items WHERE is_active = 1 "
                "AND quantity_in_stock <= minimum_stock_level "
                "ORDER BY (minimum_stock_level - quantity_in_stock) DESC LIMIT 10"
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "procurement_recommendation", "answer": "No procurement gaps detected."}
        lines = []
        for r in rows[:10]:
            need = max(0, (r.get("max_lvl") or 0) - (r.get("qty") or 0))
            lines.append(f"- {r['name']}: stock={r.get('qty')} min={r.get('min_lvl')} suggested_order={need}")
        return {"tool": "procurement_recommendation", "answer": "Reorder candidates:\n" + "\n".join(lines)}
    return _failopen(
        "procurement_recommendation",
        "Lists items at or below minimum stock with suggested reorder quantities.",
        _q,
    )


def make_expense_category_tool(period_days: int) -> Tool:
    async def _q() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT department, COUNT(*) AS c "
                "FROM expenses "
                "WHERE created_at >= NOW() - INTERVAL %s DAY "
                "GROUP BY department ORDER BY c DESC LIMIT 10",
                (period_days,),
            )
            rows = await cur.fetchall()
        if not rows:
            return {"tool": "expense_category_analysis", "answer": "No expense rows in window."}
        summary = ", ".join(f"{r['department']}={r['c']}" for r in rows[:5])
        return {
            "tool": "expense_category_analysis",
            "answer": f"Expense entry counts by department last {period_days}d: {summary}.",
        }
    return _failopen(
        "expense_category_analysis",
        "Counts expenses by category in the period.",
        _q,
    )


def make_queue_health_tool() -> Tool:
    async def _q() -> dict:
        # Read counts directly from the jobs / failed_jobs tables via clinic_ro
        try:
            async with db.cursor() as cur:
                await cur.execute("SELECT COUNT(*) AS c FROM jobs")
                pending = (await cur.fetchone()) or {"c": 0}
                await cur.execute("SELECT COUNT(*) AS c FROM failed_jobs")
                failed = (await cur.fetchone()) or {"c": 0}
        except Exception:
            return {"tool": "queue_health", "answer": "Queue tables unavailable."}
        return {
            "tool": "queue_health",
            "answer": f"Queue: pending={pending.get('c', 0)}, failed={failed.get('c', 0)}.",
        }
    return _failopen(
        "queue_health",
        "Reports Laravel queue pending and failed job counts.",
        _q,
    )
