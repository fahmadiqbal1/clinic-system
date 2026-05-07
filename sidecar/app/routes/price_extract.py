"""
Price list extraction endpoint — Phase 10C.

POST /v1/price-extract
  Accepts a file upload (PDF / image / CSV) + vendor_category string.
  Extracts price line items using regex heuristics and returns structured JSON.
  Designed for human-review-before-apply workflows — does NOT write to any table.

Schema isolations enforced upstream (in Laravel PriceExtractionService):
  - pharmaceutical/lab_supplies → updates inventory_items.purchase_price only
  - external_lab               → updates external_lab_test_prices only
  - service_catalog is NEVER touched by price uploads
"""
from __future__ import annotations

import io
import os
import re
from typing import Optional

from fastapi import APIRouter, Depends, File, Form, UploadFile
from fastapi.security import HTTPAuthorizationCredentials
from pydantic import BaseModel

from app.auth import security, verify_jwt

router = APIRouter()

# Matches lines like:
#   Hepatitis B Surface Antigen   PKR 350
#   CBC (Complete Blood Count)    Rs. 1,200.00
#   Paracetamol 500mg Tab         ₨450
#   Amoxicillin 500mg             200.00
_PRICE_RE = re.compile(
    r"([A-Za-z0-9\s\-/(),.%+]+?)\s+(?:PKR|Rs\.?|₨)?\s*(\d{1,6}(?:,\d{3})*(?:\.\d{2})?)",
    re.IGNORECASE,
)

# Lines that are almost certainly headers/footers, not items
_SKIP_PATTERNS = re.compile(
    r"(date|page|total|subtotal|discount|tax|gst|vat|amount|invoice|bill|receipt|ref|no\.|#)",
    re.IGNORECASE,
)


class PriceItem(BaseModel):
    item_name: str
    sku: Optional[str] = None
    price: float
    unit: Optional[str] = None
    confidence: float = 0.75
    needs_review: bool = False


class PriceExtractResponse(BaseModel):
    items: list[PriceItem]
    total_items: int
    flagged_items: int


def _extract_text_from_bytes(content: bytes, filename: str) -> str:
    """
    Best-effort text extraction.
    - CSV / plain text: decode directly.
    - PDF: try pdfplumber if installed, else fall back to raw byte decode.
    - Images: no text extraction in stub — return empty string.
    """
    ext = filename.rsplit(".", 1)[-1].lower() if "." in filename else ""

    if ext in ("csv", "txt"):
        try:
            return content.decode("utf-8", errors="replace")
        except Exception:
            return ""

    if ext == "pdf":
        try:
            import pdfplumber  # type: ignore

            with pdfplumber.open(io.BytesIO(content)) as pdf:
                pages = [page.extract_text() or "" for page in pdf.pages]
            return "\n".join(pages)
        except ImportError:
            pass
        # Fallback: extract printable ASCII runs from raw PDF bytes
        text_runs = re.findall(rb"[\x20-\x7e]{4,}", content)
        return "\n".join(run.decode("ascii", errors="replace") for run in text_runs)

    # Image files — cannot extract text without OCR; return empty
    return ""


def _parse_items(text: str) -> list[PriceItem]:
    items: list[PriceItem] = []
    seen: set[str] = set()

    for line in text.splitlines():
        line = line.strip()
        if not line or len(line) < 5:
            continue
        if _SKIP_PATTERNS.search(line):
            continue

        match = _PRICE_RE.search(line)
        if not match:
            continue

        raw_name = match.group(1).strip().strip(",.:;")
        raw_price_str = match.group(2).replace(",", "")

        if not raw_name or len(raw_name) < 3:
            continue

        try:
            price = float(raw_price_str)
        except ValueError:
            continue

        # Sanity check — prices outside 0–500,000 PKR are suspicious
        if price <= 0 or price > 500_000:
            needs_review = True
            confidence = 0.50
        else:
            needs_review = False
            confidence = 0.75

        # Deduplicate by normalised name
        key = raw_name.lower()
        if key in seen:
            continue
        seen.add(key)

        items.append(
            PriceItem(
                item_name=raw_name,
                price=price,
                confidence=confidence,
                needs_review=needs_review,
            )
        )

    return items


@router.post("/price-extract", response_model=PriceExtractResponse)
async def price_extract(
    file: UploadFile = File(...),
    vendor_category: str = Form(...),
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> PriceExtractResponse:
    """
    Extract price items from an uploaded price-list file.

    The extraction is purely additive — no database writes occur here.
    Laravel's PriceExtractionService handles persistence after human review.
    """
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    content = await file.read()
    filename = file.filename or "upload"

    text = _extract_text_from_bytes(content, filename)
    items = _parse_items(text)

    # If nothing could be extracted, return a flagged placeholder so the
    # human reviewer knows the file needs manual entry
    if not items:
        items = [
            PriceItem(
                item_name="[Could not extract — please enter manually]",
                price=0.0,
                confidence=0.0,
                needs_review=True,
            )
        ]

    flagged = sum(1 for i in items if i.needs_review)

    return PriceExtractResponse(
        items=items,
        total_items=len(items),
        flagged_items=flagged,
    )
