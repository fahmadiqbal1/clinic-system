"""
Smart Assistant endpoint — cross-role conversational AI.

POST /v1/assistant/chat  (multipart/form-data)
  message        : str   — user's text message
  role           : str   — logged-in role (Owner, Doctor, Pharmacy, …)
  current_page   : str   — current URL path for context
  session_id     : str   — 64-char hex for conversation memory
  file           : file  — optional uploaded file (PDF / CSV / image)

Returns JSON:
  reply                : str   — conversational response
  action               : dict  — optional action hint {type, label, url, data}
  clarifying_question  : str   — optional follow-up question
"""
from __future__ import annotations

import json
import os
import re
from typing import Optional

from fastapi import APIRouter, Depends, File, Form, UploadFile
from fastapi.security import HTTPAuthorizationCredentials

from app.auth import security, verify_jwt
from app.agent.model_provider import call_model
from app.routes.price_extract import _extract_text_from_bytes

router = APIRouter()

# ---------------------------------------------------------------------------
# Role-aware context definitions
# ---------------------------------------------------------------------------
_ROLE_CTX: dict[str, dict] = {
    "owner": {
        "description": "clinic owner of Aviva HealthCare Clinic, DHA Lahore",
        "can_do": (
            "vendor management, price list uploads, staff performance analytics, "
            "procurement approvals, financial oversight, compliance checks, "
            "KPI dashboards, admin/ops/compliance AI analysis"
        ),
        "actions": ["upload_price_list", "navigate", "run_admin_analysis", "run_ops_analysis"],
        "action_map": {
            "price list":       {"type": "navigate", "label": "Go to Vendors", "url": "/owner/vendors"},
            "vendor":           {"type": "navigate", "label": "Go to Vendors", "url": "/owner/vendors"},
            "staff":            {"type": "navigate", "label": "Staff Performance", "url": "/owner/performance-matrix"},
            "expense":          {"type": "navigate", "label": "Expense Intelligence", "url": "/owner/expense-intelligence"},
            "procurement":      {"type": "navigate", "label": "Procurement", "url": "/owner/procurement"},
            "compliance":       {"type": "navigate", "label": "Compliance AI", "url": "/owner/compliance-ai"},
            "payout":           {"type": "navigate", "label": "Payouts", "url": "/owner/payouts"},
            "attendance":       {"type": "navigate", "label": "Attendance", "url": "/owner/attendance"},
            "dashboard":        {"type": "navigate", "label": "Dashboard", "url": "/owner/dashboard"},
            "financial":        {"type": "navigate", "label": "Financial Report", "url": "/owner/financial-report"},
            "fbr":              {"type": "navigate", "label": "FBR Settings", "url": "/owner/fbr-settings"},
            "contract":         {"type": "navigate", "label": "Staff Contracts", "url": "/owner/staff-contracts"},
            "kpi":              {"type": "navigate", "label": "KPI Dashboard", "url": "/owner/kpi-dashboard"},
        },
    },
    "doctor": {
        "description": "doctor at Aviva HealthCare Clinic",
        "can_do": "patient consultations, prescription creation, lab/radiology order review, clinical AI assistant queries",
        "actions": ["navigate"],
        "action_map": {
            "patient":       {"type": "navigate", "label": "Patient Queue", "url": "/doctor/dashboard"},
            "prescription":  {"type": "navigate", "label": "Prescriptions", "url": "/doctor/dashboard"},
            "consultation":  {"type": "navigate", "label": "Consultations", "url": "/doctor/dashboard"},
            "dashboard":     {"type": "navigate", "label": "Dashboard", "url": "/doctor/dashboard"},
        },
    },
    "pharmacy": {
        "description": "pharmacist at Aviva HealthCare Clinic",
        "can_do": "prescription dispensing, inventory checking, stock level alerts, price list management",
        "actions": ["navigate"],
        "action_map": {
            "prescription":  {"type": "navigate", "label": "Prescription Queue", "url": "/pharmacy/dashboard"},
            "inventory":     {"type": "navigate", "label": "Inventory", "url": "/pharmacy/inventory"},
            "stock":         {"type": "navigate", "label": "Inventory", "url": "/pharmacy/inventory"},
            "dashboard":     {"type": "navigate", "label": "Dashboard", "url": "/pharmacy/dashboard"},
        },
    },
    "laboratory": {
        "description": "lab technician at Aviva HealthCare Clinic",
        "can_do": "lab test processing, result entry, pending order management",
        "actions": ["navigate"],
        "action_map": {
            "test":      {"type": "navigate", "label": "Lab Dashboard", "url": "/laboratory/dashboard"},
            "result":    {"type": "navigate", "label": "Lab Dashboard", "url": "/laboratory/dashboard"},
            "pending":   {"type": "navigate", "label": "Lab Dashboard", "url": "/laboratory/dashboard"},
            "dashboard": {"type": "navigate", "label": "Dashboard", "url": "/laboratory/dashboard"},
        },
    },
    "radiology": {
        "description": "radiologist at Aviva HealthCare Clinic",
        "can_do": "radiology scan processing, result entry, pending scan orders",
        "actions": ["navigate"],
        "action_map": {
            "scan":      {"type": "navigate", "label": "Radiology Dashboard", "url": "/radiology/dashboard"},
            "result":    {"type": "navigate", "label": "Radiology Dashboard", "url": "/radiology/dashboard"},
            "dashboard": {"type": "navigate", "label": "Dashboard", "url": "/radiology/dashboard"},
        },
    },
    "triage": {
        "description": "triage nurse at Aviva HealthCare Clinic",
        "can_do": "patient registration, vital signs recording, queue management",
        "actions": ["navigate"],
        "action_map": {
            "patient":   {"type": "navigate", "label": "Triage Queue", "url": "/triage/dashboard"},
            "vitals":    {"type": "navigate", "label": "Triage Queue", "url": "/triage/dashboard"},
            "dashboard": {"type": "navigate", "label": "Dashboard", "url": "/triage/dashboard"},
        },
    },
    "receptionist": {
        "description": "receptionist at Aviva HealthCare Clinic",
        "can_do": "patient registration, appointment scheduling, invoicing",
        "actions": ["navigate"],
        "action_map": {
            "patient":      {"type": "navigate", "label": "Register Patient", "url": "/receptionist/patients/create"},
            "invoice":      {"type": "navigate", "label": "Create Invoice", "url": "/receptionist/invoices/create"},
            "appointment":  {"type": "navigate", "label": "Dashboard", "url": "/receptionist/dashboard"},
            "dashboard":    {"type": "navigate", "label": "Dashboard", "url": "/receptionist/dashboard"},
        },
    },
}

_FALLBACK_CTX = {
    "description": "staff member at Aviva HealthCare Clinic",
    "can_do": "navigating the system",
    "actions": ["navigate"],
    "action_map": {},
}


def _build_system_prompt(role: str, current_page: str, has_file: bool, file_text: str) -> str:
    ctx = _ROLE_CTX.get(role.lower(), _FALLBACK_CTX)
    file_section = ""
    if has_file:
        preview = file_text[:2000].strip() if file_text else ""
        file_section = f"""
The user has uploaded a file. Extracted text preview:
---
{preview or "(could not extract text — binary/image file)"}
---
Based on this content, identify what the file is and what action makes sense.
"""

    return f"""You are a smart AI assistant embedded in the Aviva HealthCare Clinic management system.
You are helping a {ctx["description"]}.
You can assist with: {ctx["can_do"]}.
The user is currently on page: {current_page}
{file_section}
Respond in strict JSON with this exact structure:
{{
  "reply": "<conversational response, max 60 words, direct and helpful>",
  "action": {{
    "type": "<one of: {", ".join(ctx["actions"])} or null>",
    "label": "<short button label, e.g. 'Go to Vendors'>",
    "url": "<relative URL or null>",
    "data": {{}}
  }},
  "clarifying_question": "<one short question if intent is ambiguous, otherwise null>"
}}
Rules:
- reply is always present
- If intent is clear → set action, set clarifying_question to null
- If intent is ambiguous → set action to null, set clarifying_question
- For file uploads: identify what the file is and suggest the right action
- For Owner + price list PDF → action type = "upload_price_list", url = "/owner/vendors"
- Never set both action and clarifying_question simultaneously
- Output only the JSON object, no markdown fences"""


def _extract_action_from_keywords(message: str, role: str) -> Optional[dict]:
    """Keyword fallback if model output can't be parsed."""
    ctx = _ROLE_CTX.get(role.lower(), _FALLBACK_CTX)
    msg_lower = message.lower()
    for keyword, action in ctx.get("action_map", {}).items():
        if keyword in msg_lower:
            return action
    return None


@router.post("/assistant/chat")
async def assistant_chat(
    message: str = Form(""),
    role: str = Form("user"),
    current_page: str = Form("/"),
    session_id: str = Form(""),
    file: Optional[UploadFile] = File(None),
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> dict:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    # Extract file text if provided
    file_text = ""
    has_file = False
    file_name = ""
    if file and file.filename:
        has_file = True
        file_name = file.filename
        content = await file.read()
        file_text = _extract_text_from_bytes(content, file_name)

    system_prompt = _build_system_prompt(role, current_page, has_file, file_text)

    user_content = message or ""
    if has_file and not user_content:
        user_content = f"I've uploaded a file: {file_name}"
    elif has_file:
        user_content = f"{message} [File attached: {file_name}]"

    messages = [
        {"role": "system", "content": system_prompt},
        {"role": "user",   "content": user_content},
    ]

    raw = await call_model(messages)

    # Parse model JSON response
    try:
        # Strip markdown fences if model wraps in ```json
        cleaned = re.sub(r"^```(?:json)?\s*|\s*```$", "", raw.strip(), flags=re.MULTILINE)
        parsed = json.loads(cleaned)
    except (json.JSONDecodeError, ValueError):
        # Fallback: plain reply with keyword-based action
        parsed = {
            "reply": raw[:300] if raw else "I'm here to help — what do you need?",
            "action": None,
            "clarifying_question": None,
        }
        kw_action = _extract_action_from_keywords(user_content, role)
        if kw_action:
            parsed["action"] = kw_action

    # Ensure required fields
    parsed.setdefault("reply", "How can I help you?")
    parsed.setdefault("action", None)
    parsed.setdefault("clarifying_question", None)

    # Special handling: price list upload → ensure correct action type
    if has_file and role.lower() == "owner":
        text_lower = (file_text + file_name).lower()
        if any(kw in text_lower for kw in ["price", "pkr", "rs.", "medicine", "tablet", "capsule", "syrup"]):
            if not parsed.get("action") or parsed["action"].get("type") != "upload_price_list":
                parsed["action"] = {
                    "type": "upload_price_list",
                    "label": "Upload as Price List",
                    "url": "/owner/vendors",
                    "data": {"file_name": file_name},
                }

    return parsed
