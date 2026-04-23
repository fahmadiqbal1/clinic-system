import hashlib
import os

import httpx
from fastapi import APIRouter, Depends, HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from app.auth import security, verify_jwt
from app.schemas.medical import ConsultInput, ConsultOutput, VitalSet

router = APIRouter()


@router.post("/consult", response_model=ConsultOutput)
async def consult(
    body: ConsultInput,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> ConsultOutput:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    prompt = _build_prompt(body)
    prompt_hash = hashlib.sha256(prompt.encode()).hexdigest()

    ollama_url = os.environ.get("OLLAMA_URL", "http://ollama:11434")
    model = os.environ.get("OLLAMA_MODEL", "medgemma")

    try:
        async with httpx.AsyncClient(timeout=120.0) as client:
            resp = await client.post(
                f"{ollama_url}/v1/chat/completions",
                json={
                    "model": model,
                    "messages": [{"role": "user", "content": prompt}],
                    "max_tokens": 2048,
                    "temperature": 0.3,
                },
                headers={"bypass-tunnel-reminder": "true"},
            )
    except httpx.RequestError as exc:
        raise HTTPException(status_code=503, detail=f"Ollama unreachable: {exc}")

    if resp.status_code != 200:
        raise HTTPException(status_code=503, detail=f"Ollama returned {resp.status_code}")

    data = resp.json()
    rationale = (
        data.get("choices", [{}])[0].get("message", {}).get("content")
        or "No response generated."
    )

    return ConsultOutput(
        model_id=f"{model}:sidecar",
        prompt_hash=prompt_hash,
        rationale=rationale,
        confidence=0.75,
        requires_human_review=True,
        retrieval_citations=[],
    )


def _build_prompt(body: ConsultInput) -> str:
    lines = ["You are MedGemma, an AI medical assistant providing a second opinion.\n"]
    lines.append(f"Case Token: {body.case_token}, Gender: {body.gender}, Age Band: {body.age_band}")

    if body.vitals:
        v: VitalSet = body.vitals
        lines.append("\n--- Vital Signs ---")
        if v.bp_systolic is not None and v.bp_diastolic is not None:
            lines.append(f"Blood Pressure: {v.bp_systolic}/{v.bp_diastolic} mmHg")
        if v.heart_rate is not None:
            lines.append(f"Heart Rate: {v.heart_rate} bpm")
        if v.temperature_c is not None:
            lines.append(f"Temperature: {v.temperature_c}°C")
        if v.spo2 is not None:
            lines.append(f"SpO2: {v.spo2}%")
        if v.chief_complaint:
            lines.append(f"Chief Complaint: {v.chief_complaint}")

    if body.medications:
        lines.append("\n--- Medications ---")
        lines.extend(body.medications)

    for lab in body.lab_results:
        lines.append(f"\n--- Lab: {lab.panel_name} ---")
        for r in lab.results:
            lines.append(f"{r.test_name}: {r.result} {r.unit or ''} (Ref: {r.reference_range or 'N/A'})")

    for rad in body.radiology:
        lines.append(f"\n--- Radiology: {rad.imaging_type} ---")
        if rad.findings:
            lines.append(rad.findings)

    if body.custom_question:
        lines.append(f"\n---\nDOCTOR'S QUESTION:\n{body.custom_question}")

    lines.append("\nProvide comprehensive clinical assessment, differential diagnoses, and recommendations.")
    return "\n".join(lines)
