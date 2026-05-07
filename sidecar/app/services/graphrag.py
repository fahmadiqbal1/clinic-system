"""
GraphRAG orchestrator — Phase 9A.

Combines two retrieval strategies before LLM inference:
  1. Knowledge Graph (graph.py)  — deterministic, structured facts from MySQL
  2. RAGFlow                     — fuzzy semantic retrieval from unstructured docs

The combined context is formatted so the LLM can clearly distinguish hard facts
(which it should not contradict) from guideline context (which is suggestive).

All steps are fail-open: if graph retrieval fails, the RAG answer still reaches
the model; if RAG is down, the graph facts alone are returned.
"""
from __future__ import annotations

import json
import logging
from typing import Any

from app.services import graph, ragflow

logger = logging.getLogger(__name__)


def _format_graph_facts(facts: dict) -> str:
    try:
        return json.dumps(facts, indent=2, default=str)
    except Exception:
        return str(facts)


async def graphrag_retrieve(
    query: str,
    context_hints: dict[str, Any] | None = None,
) -> dict:
    """
    Retrieve enriched context for an LLM prompt.

    Args:
        query:         The clinical or operational question.
        context_hints: Optional dict with:
                       - patient_id  (int)  — triggers patient drug graph + allergy check
                       - drug_names  (list) — list of drug names for allergy cross-check

    Returns:
        {
            graph_facts:      dict   — raw structured data from graph traversals,
            rag_context:      str    — RAGFlow answer text,
            citations:        list   — RAGFlow citation strings,
            combined_context: str    — merged prompt-ready string,
        }
    """
    hints = context_hints or {}
    patient_id: int | None = hints.get("patient_id")
    drug_names: list[str] = list(hints.get("drug_names") or [])

    graph_facts: dict = {}

    if patient_id is not None:
        try:
            graph_facts["patient_drug_graph"] = await graph.patient_drug_graph(int(patient_id))
        except Exception as exc:
            logger.warning("graphrag_retrieve: patient_drug_graph failed: %s", exc)
            graph_facts["patient_drug_graph"] = {"error": str(exc)}

        if drug_names:
            try:
                graph_facts["allergy_check"] = await graph.allergy_contraindication_check(
                    int(patient_id), drug_names
                )
            except Exception as exc:
                logger.warning("graphrag_retrieve: allergy_check failed: %s", exc)
                graph_facts["allergy_check"] = {"error": str(exc)}

    rag_answer = ""
    citations: list[str] = []
    try:
        rag_result = await ragflow.query(query, collection="general")
        rag_answer = rag_result.get("answer", "")
        citations = rag_result.get("citations", [])
    except Exception as exc:
        logger.warning("graphrag_retrieve: RAGFlow query failed: %s", exc)

    graph_section = (
        _format_graph_facts(graph_facts)
        if graph_facts
        else "(no graph data available)"
    )
    rag_section = rag_answer if rag_answer else "(no guideline context available)"

    combined_context = (
        "## Hard Facts (Knowledge Graph)\n"
        f"{graph_section}\n\n"
        "## Guidelines (RAG)\n"
        f"{rag_section}"
    )

    return {
        "graph_facts":      graph_facts,
        "rag_context":      rag_answer,
        "citations":        citations,
        "combined_context": combined_context,
    }
