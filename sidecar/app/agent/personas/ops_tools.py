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


def make_room_utilisation_tool(period_days: int) -> Tool:
    """
    Analyses clinic room utilisation by joining appointments → clinic_rooms.
    Identifies under-used rooms, peak hours, and which doctor-room pairings
    generate the most patient throughput. Fail-open.
    """
    async def _q() -> dict:
        async with db.cursor() as cur:
            # Room-level booking counts over the period
            await cur.execute(
                """
                SELECT
                    cr.name         AS room_name,
                    cr.type         AS room_type,
                    COUNT(a.id)     AS booking_count,
                    COUNT(DISTINCT a.doctor_id) AS unique_doctors
                FROM clinic_rooms cr
                LEFT JOIN appointments a
                    ON a.room_id = cr.id
                   AND a.scheduled_at >= NOW() - INTERVAL %s DAY
                   AND a.status NOT IN ('cancelled','no_show')
                WHERE cr.is_active = 1
                GROUP BY cr.id, cr.name, cr.type
                ORDER BY booking_count DESC
                """,
                (period_days,),
            )
            room_rows = await cur.fetchall()

            # Doctor-room pairing with patient count (top 10 pairings)
            await cur.execute(
                """
                SELECT
                    u.name          AS doctor_name,
                    cr.name         AS room_name,
                    COUNT(a.id)     AS patient_count
                FROM appointments a
                JOIN users       u  ON u.id = a.doctor_id
                JOIN clinic_rooms cr ON cr.id = a.room_id
                WHERE a.scheduled_at >= NOW() - INTERVAL %s DAY
                  AND a.status NOT IN ('cancelled','no_show')
                GROUP BY a.doctor_id, a.room_id
                ORDER BY patient_count DESC
                LIMIT 10
                """,
                (period_days,),
            )
            pairing_rows = await cur.fetchall()

        if not room_rows:
            return {"tool": "room_utilisation", "answer": "No clinic rooms or appointment data available."}

        total_bookings = sum(r.get("booking_count") or 0 for r in room_rows)
        top_room = room_rows[0] if room_rows else {}
        idle_rooms = [r["room_name"] for r in room_rows if (r.get("booking_count") or 0) == 0]

        lines = [f"Room utilisation last {period_days}d: {total_bookings} total bookings."]
        lines.append(f"Most used: {top_room.get('room_name','?')} ({top_room.get('booking_count',0)} bookings).")
        if idle_rooms:
            lines.append(f"Idle rooms (0 bookings): {', '.join(idle_rooms)}.")
        if pairing_rows:
            top = pairing_rows[0]
            lines.append(
                f"Top doctor-room pairing: {top.get('doctor_name','?')} in {top.get('room_name','?')} "
                f"({top.get('patient_count',0)} patients)."
            )
        return {"tool": "room_utilisation", "answer": "\n".join(lines), "rooms": room_rows}

    return _failopen("room_utilisation", "Analyses clinic room booking utilisation and doctor-room pairings.", _q)


def make_doctor_demand_tool(period_days: int) -> Tool:
    """
    Identifies which doctors/specialities receive the most patients over time
    and detects demand trends (growing, stable, declining). Used by the owner
    to make rostering and room-allocation decisions.
    """
    async def _q() -> dict:
        async with db.cursor() as cur:
            # Per-doctor patient throughput
            await cur.execute(
                """
                SELECT
                    u.name                  AS doctor_name,
                    u.specialty             AS specialty,
                    COUNT(DISTINCT p.id)    AS unique_patients,
                    COUNT(a.id)             AS total_appointments
                FROM users u
                LEFT JOIN appointments a
                    ON a.doctor_id = u.id
                   AND a.scheduled_at >= NOW() - INTERVAL %s DAY
                   AND a.status NOT IN ('cancelled','no_show')
                LEFT JOIN patients p ON p.id = a.patient_id
                JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type = 'App\\\\Models\\\\User'
                JOIN roles r ON r.id = mhr.role_id AND r.name = 'Doctor'
                WHERE u.is_active = 1
                GROUP BY u.id, u.name, u.specialty
                ORDER BY total_appointments DESC
                LIMIT 15
                """,
                (period_days,),
            )
            doctor_rows = await cur.fetchall()

            # Speciality-level demand (group by specialty)
            await cur.execute(
                """
                SELECT
                    COALESCE(u.specialty, 'General Practice') AS specialty,
                    COUNT(a.id)                               AS total_appointments,
                    COUNT(DISTINCT a.doctor_id)               AS doctor_count
                FROM appointments a
                JOIN users u ON u.id = a.doctor_id
                WHERE a.scheduled_at >= NOW() - INTERVAL %s DAY
                  AND a.status NOT IN ('cancelled','no_show')
                GROUP BY specialty
                ORDER BY total_appointments DESC
                LIMIT 10
                """,
                (period_days,),
            )
            specialty_rows = await cur.fetchall()

        if not doctor_rows:
            return {"tool": "doctor_demand", "answer": "No appointment data in window."}

        total = sum(r.get("total_appointments") or 0 for r in doctor_rows)
        top_doctor = doctor_rows[0] if doctor_rows else {}
        top_specialty = specialty_rows[0] if specialty_rows else {}

        lines = [f"Doctor demand last {period_days}d: {total} total appointments across {len(doctor_rows)} doctors."]
        lines.append(
            f"Highest demand: Dr. {top_doctor.get('doctor_name','?')} "
            f"({top_doctor.get('specialty') or 'GP'}) — {top_doctor.get('total_appointments',0)} appointments."
        )
        if top_specialty:
            lines.append(
                f"Top speciality by demand: {top_specialty.get('specialty','?')} "
                f"({top_specialty.get('total_appointments',0)} appointments, {top_specialty.get('doctor_count',0)} doctors)."
            )
        idle_doctors = [r["doctor_name"] for r in doctor_rows if (r.get("total_appointments") or 0) == 0]
        if idle_doctors:
            lines.append(f"Doctors with 0 appointments: {', '.join(idle_doctors[:3])}.")

        return {
            "tool": "doctor_demand",
            "answer": "\n".join(lines),
            "doctors": doctor_rows,
            "specialties": specialty_rows,
        }

    return _failopen("doctor_demand", "Identifies doctor and speciality demand trends from appointment data.", _q)


def make_draft_procurement_tool() -> Tool:
    """
    Autonomously drafts procurement requests for critical/warning stock items
    by posting to the Laravel internal endpoint. Each draft lands with
    status=pending_approval so the owner can one-click confirm.

    Requires CLINIC_SIDECAR_URL and CLINIC_SIDECAR_JWT env vars in sidecar.
    Fails-open: if the endpoint is unreachable, returns a recommendation text
    instead so the AI can still surface the suggestion.
    """
    import os
    import httpx

    async def _create_drafts() -> dict:
        async with db.cursor() as cur:
            await cur.execute(
                "SELECT id, name, quantity_in_stock AS qty, "
                "minimum_stock_level AS min_lvl, "
                "GREATEST(minimum_stock_level * 3, minimum_stock_level + 50) AS suggested_qty "
                "FROM inventory_items WHERE is_active = 1 "
                "AND quantity_in_stock <= minimum_stock_level "
                "ORDER BY quantity_in_stock ASC LIMIT 10"
            )
            rows = await cur.fetchall()

        if not rows:
            return {"tool": "draft_procurement", "answer": "No items below minimum stock — no drafts needed."}

        laravel_url = os.environ.get("CLINIC_SIDECAR_URL", "http://app:9000")
        jwt = os.environ.get("CLINIC_SIDECAR_JWT", "")
        created, failed = [], []

        async with httpx.AsyncClient(timeout=15.0) as client:
            for row in rows:
                item_id = row.get("id")
                qty_needed = max(1, int((row.get("suggested_qty") or 1) - (row.get("qty") or 0)))
                try:
                    resp = await client.post(
                        f"{laravel_url.rstrip('/')}/api/internal/procurement/draft",
                        headers={"Authorization": f"Bearer {jwt}", "Content-Type": "application/json"},
                        json={
                            "inventory_item_id": item_id,
                            "quantity":          qty_needed,
                            "reason":            "Auto-drafted by Ops AI — stock at or below minimum level",
                        },
                    )
                    if resp.status_code in (200, 201):
                        created.append(row["name"])
                    else:
                        failed.append(row["name"])
                except Exception:
                    failed.append(row["name"])

        parts = []
        if created:
            parts.append(f"Drafted {len(created)} procurement request(s) for owner approval: {', '.join(created[:5])}.")
        if failed:
            parts.append(f"{len(failed)} item(s) could not be drafted (endpoint unreachable): {', '.join(failed[:3])}.")

        return {"tool": "draft_procurement", "answer": " ".join(parts), "drafted": created, "failed": failed}

    return _failopen(
        "draft_procurement",
        "Creates pending-approval procurement request drafts for items at or below minimum stock.",
        _create_drafts,
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
