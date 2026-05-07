"""
MySQL knowledge graph service — Phase 9A.

Treats relational tables as graph nodes/edges and exposes named traversals
via Recursive CTEs. All functions fail-open — callers degrade gracefully
when the DB is unavailable.

Nodes:  patients, service_catalog, users, inventory_items
Edges:  prescriptions → prescription_items, visits, stock_movements,
        procurement_requests
"""
from __future__ import annotations

import logging
from typing import Any

from app.services.db import cursor

logger = logging.getLogger(__name__)


async def drug_recall_impact(drug_name: str) -> dict:
    """
    Walk prescriptions → prescription_items → service_catalog → patients
    to find every patient who received a named drug. Uses a recursive CTE
    stub (self-join anchor) so the query can be extended later for multi-hop
    relationships (e.g. drug → drug family).
    """
    try:
        sql = """
            WITH RECURSIVE affected AS (
                SELECT
                    p.id          AS patient_id,
                    p.name        AS patient_name,
                    pr.id         AS prescription_id,
                    pr.created_at AS prescribed_at,
                    sc.name       AS drug_name
                FROM prescriptions pr
                JOIN prescription_items pi ON pr.id = pi.prescription_id
                JOIN service_catalog    sc ON pi.service_catalog_id = sc.id
                JOIN patients            p ON pr.patient_id = p.id
                WHERE sc.name LIKE %s
                UNION ALL
                -- Recursive anchor: extend for drug-family relationships here
                SELECT a.patient_id, a.patient_name, a.prescription_id,
                       a.prescribed_at, a.drug_name
                FROM affected a
                WHERE 1 = 0
            )
            SELECT patient_id, patient_name, prescription_id,
                   prescribed_at, drug_name
            FROM affected
            ORDER BY prescribed_at DESC
        """
        async with cursor() as cur:
            await cur.execute(sql, (f"%{drug_name}%",))
            rows = await cur.fetchall()

        patients = [
            {
                "patient_id":      r["patient_id"],
                "patient_name":    r["patient_name"],
                "prescription_id": r["prescription_id"],
                "prescribed_at":   str(r["prescribed_at"]),
                "drug_name":       r["drug_name"],
            }
            for r in (rows or [])
        ]
        return {"patients": patients, "count": len(patients), "drug_name": drug_name}
    except Exception as exc:
        logger.warning("drug_recall_impact failed for %r: %s", drug_name, exc)
        return {"error": str(exc), "patients": [], "count": 0, "drug_name": drug_name}


async def patient_drug_graph(patient_id: int) -> dict:
    """
    All prescriptions + drugs for a patient, including the prescribing doctor.
    Returns a grouped structure: prescription → drugs list.
    """
    try:
        sql = """
            SELECT
                pr.id              AS prescription_id,
                pr.created_at      AS prescribed_at,
                pr.status          AS prescription_status,
                u.name             AS doctor_name,
                sc.name            AS drug_name,
                pi.quantity,
                pi.instructions
            FROM prescriptions pr
            JOIN users               u  ON pr.doctor_id = u.id
            JOIN prescription_items  pi ON pr.id = pi.prescription_id
            JOIN service_catalog     sc ON pi.service_catalog_id = sc.id
            WHERE pr.patient_id = %s
            ORDER BY pr.created_at DESC
        """
        async with cursor() as cur:
            await cur.execute(sql, (patient_id,))
            rows = await cur.fetchall()

        prescriptions: dict[int, dict] = {}
        for r in (rows or []):
            pid = r["prescription_id"]
            if pid not in prescriptions:
                prescriptions[pid] = {
                    "prescription_id": pid,
                    "prescribed_at":   str(r["prescribed_at"]),
                    "status":          r["prescription_status"],
                    "doctor":          r["doctor_name"],
                    "drugs":           [],
                }
            prescriptions[pid]["drugs"].append(
                {
                    "name":         r["drug_name"],
                    "quantity":     r["quantity"],
                    "instructions": r["instructions"],
                }
            )

        return {
            "patient_id":           patient_id,
            "prescriptions":        list(prescriptions.values()),
            "total_prescriptions":  len(prescriptions),
        }
    except Exception as exc:
        logger.warning("patient_drug_graph failed for patient %s: %s", patient_id, exc)
        return {
            "error":               str(exc),
            "patient_id":          patient_id,
            "prescriptions":       [],
            "total_prescriptions": 0,
        }


async def allergy_contraindication_check(
    patient_id: int,
    drug_names: list[str],
) -> dict:
    """
    Heuristic contraindication check: scans past visit diagnoses for mentions
    of the given drug names. Until a dedicated allergies table exists, this
    catches documented contraindications recorded in free-text diagnosis fields.
    """
    if not drug_names:
        return {"warnings": [], "safe": True, "patient_id": patient_id}

    try:
        like_clauses = " OR ".join(["v.diagnosis LIKE %s"] * len(drug_names))
        sql = f"""
            SELECT v.id AS visit_id, v.visited_at, v.diagnosis
            FROM visits v
            WHERE v.patient_id = %s
              AND ({like_clauses})
            ORDER BY v.visited_at DESC
        """
        params: list[Any] = [patient_id] + [f"%{d}%" for d in drug_names]

        async with cursor() as cur:
            await cur.execute(sql, params)
            rows = await cur.fetchall()

        warnings: list[dict] = []
        for r in (rows or []):
            for drug in drug_names:
                if drug.lower() in (r["diagnosis"] or "").lower():
                    warnings.append(
                        {
                            "drug":              drug,
                            "visit_id":          r["visit_id"],
                            "visited_at":        str(r["visited_at"]),
                            "diagnosis_snippet": (r["diagnosis"] or "")[:200],
                        }
                    )

        return {
            "warnings":     warnings,
            "safe":         len(warnings) == 0,
            "patient_id":   patient_id,
            "drugs_checked": drug_names,
        }
    except Exception as exc:
        logger.warning(
            "allergy_contraindication_check failed for patient %s: %s", patient_id, exc
        )
        return {
            "error":        str(exc),
            "warnings":     [],
            "safe":         True,
            "patient_id":   patient_id,
            "drugs_checked": drug_names,
        }


async def inventory_supply_chain(item_name: str) -> dict:
    """
    Walk inventory_items → stock_movements → procurement_requests to expose
    the supply chain for a named item.
    """
    try:
        async with cursor() as cur:
            await cur.execute(
                "SELECT id, name, sku, quantity_in_stock AS quantity, "
                "minimum_stock_level AS min_stock_level "
                "FROM inventory_items WHERE name LIKE %s LIMIT 1",
                (f"%{item_name}%",),
            )
            item_row = await cur.fetchone()

            if not item_row:
                return {
                    "item":       None,
                    "movements":  [],
                    "procurement": [],
                    "item_name":  item_name,
                }

            item_id = item_row["id"]

            movements: list[dict] = []
            try:
                await cur.execute(
                    "SELECT id, quantity, type, created_at "
                    "FROM stock_movements "
                    "WHERE inventory_item_id = %s ORDER BY created_at DESC LIMIT 20",
                    (item_id,),
                )
                movements = [
                    {
                        "id":         r["id"],
                        "quantity":   r["quantity"],
                        "type":       r["type"],
                        "created_at": str(r["created_at"]),
                    }
                    for r in (await cur.fetchall() or [])
                ]
            except Exception as exc:
                logger.debug("stock_movements query failed: %s", exc)

            procurement: list[dict] = []
            try:
                # procurement_requests are linked via procurement_request_items
                await cur.execute(
                    """
                    SELECT pr.id, pri.quantity_requested AS quantity,
                           pr.status, pr.created_at
                    FROM procurement_requests pr
                    JOIN procurement_request_items pri ON pri.procurement_request_id = pr.id
                    WHERE pri.inventory_item_id = %s
                    ORDER BY pr.created_at DESC LIMIT 10
                    """,
                    (item_id,),
                )
                procurement = [
                    {
                        "id":         r["id"],
                        "quantity":   r["quantity"],
                        "status":     r["status"],
                        "created_at": str(r["created_at"]),
                    }
                    for r in (await cur.fetchall() or [])
                ]
            except Exception as exc:
                logger.debug("procurement_requests query failed: %s", exc)

        return {
            "item": {
                "id":              item_row["id"],
                "name":            item_row["name"],
                "sku":             item_row.get("sku"),
                "current_qty":     item_row["quantity"],
                "min_stock_level": item_row["min_stock_level"],
            },
            "movements":  movements,
            "procurement": procurement,
            "item_name":  item_name,
        }
    except Exception as exc:
        logger.warning("inventory_supply_chain failed for %r: %s", item_name, exc)
        return {
            "error":      str(exc),
            "item":       None,
            "movements":  [],
            "procurement": [],
            "item_name":  item_name,
        }


async def graph_query(query_type: str, params: dict) -> dict:
    """Generic dispatcher for named graph traversals."""
    try:
        if query_type == "drug_recall":
            return await drug_recall_impact(params["drug_name"])
        elif query_type == "patient_drugs":
            return await patient_drug_graph(int(params["patient_id"]))
        elif query_type == "allergy_check":
            return await allergy_contraindication_check(
                int(params["patient_id"]),
                list(params.get("drug_names", [])),
            )
        elif query_type == "supply_chain":
            return await inventory_supply_chain(params["item_name"])
        else:
            return {"error": f"Unknown query_type: {query_type!r}", "query_type": query_type}
    except Exception as exc:
        logger.warning("graph_query(%r) failed: %s", query_type, exc)
        return {"error": str(exc), "query_type": query_type}
