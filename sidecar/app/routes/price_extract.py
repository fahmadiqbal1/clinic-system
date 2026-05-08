"""
Price list extraction endpoint.

POST /v1/price-extract
  Accepts a file upload (PDF / CSV) + vendor_category string.
  For PDFs, uses pdfplumber table extraction — deterministic, 100% confidence.
  For CSVs, falls back to header-mapping logic.
  Returns structured JSON; does NOT write to any table.

PDF layout assumption:
  Two-column layout per page. Each row has 8 cells:
    [SKU, SKU DESC, PACK SIZE, T.P,  SKU, SKU DESC, PACK SIZE, T.P]
  This matches the Faisalabad / Novartis price list format.
  If a PDF uses a different layout, pdfplumber still extracts the table
  and the column sniffer maps whichever header names it finds.
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


class PriceItem(BaseModel):
    item_name: str
    sku: Optional[str] = None
    pack_size: Optional[str] = None
    price: float
    unit: Optional[str] = None        # kept for backwards-compat; same as pack_size
    confidence: float = 1.0
    needs_review: bool = False


class PriceExtractResponse(BaseModel):
    items: list[PriceItem]
    total_items: int
    flagged_items: int


# ── Column name aliases ────────────────────────────────────────────────────────

_SKU_HEADERS   = {"sku", "code", "item code", "article no", "article", "barcode"}
_DESC_HEADERS  = {"sku desc", "description", "item", "product", "name", "item name",
                  "medicine", "drug name", "product name"}
_PACK_HEADERS  = {"pack size", "pack", "size", "unit", "uom", "packing", "qty", "pack_size"}
_PRICE_HEADERS = {"t.p", "tp", "price", "unit price", "rate", "mrp", "cost",
                  "trade price", "trade_price", "amount"}


def _col_index(headers: list[str], aliases: set[str]) -> Optional[int]:
    for i, h in enumerate(headers):
        if h.lower().strip() in aliases:
            return i
    return None


def _parse_price(raw: str) -> Optional[float]:
    cleaned = re.sub(r"[^\d.]", "", raw.replace(",", ""))
    try:
        return float(cleaned) if cleaned else None
    except ValueError:
        return None


# ── PDF extraction ─────────────────────────────────────────────────────────────

def _extract_pdf(content: bytes) -> list[PriceItem]:
    """
    Use pdfplumber table extraction.  Handles the two-column layout by
    treating all 8 cells as two logical rows per physical table row.
    """
    import pdfplumber  # type: ignore

    items: list[PriceItem] = []
    seen: set[str] = set()

    with pdfplumber.open(io.BytesIO(content)) as pdf:
        for page in pdf.pages:
            tables = page.extract_tables()
            if not tables:
                continue

            for table in tables:
                if not table:
                    continue

                # Detect header row — find the row that contains a SKU/description header
                header_row_idx = None
                sku_col = desc_col = pack_col = price_col = None
                right_sku = right_desc = right_pack = right_price = None

                for row_idx, row in enumerate(table):
                    cells = [str(c or "").strip() for c in row]
                    # Try to find column mapping in this row
                    s = _col_index(cells, _SKU_HEADERS)
                    d = _col_index(cells, _DESC_HEADERS)
                    p = _col_index(cells, _PRICE_HEADERS)
                    if s is not None and d is not None and p is not None:
                        header_row_idx = row_idx
                        sku_col   = s
                        desc_col  = d
                        pack_col  = _col_index(cells, _PACK_HEADERS)
                        price_col = p

                        # Check if there's a mirrored second set (two-column layout)
                        # Typically offset by 4 in 8-wide tables
                        n = len(cells)
                        if n >= 8:
                            offset = n // 2
                            right_cells = cells[offset:]
                            rs = _col_index(right_cells, _SKU_HEADERS)
                            rd = _col_index(right_cells, _DESC_HEADERS)
                            rp = _col_index(right_cells, _PRICE_HEADERS)
                            if rs is not None and rd is not None and rp is not None:
                                right_sku   = offset + rs
                                right_desc  = offset + rd
                                right_price = offset + rp
                                right_pack  = (
                                    offset + _col_index(right_cells, _PACK_HEADERS)
                                    if _col_index(right_cells, _PACK_HEADERS) is not None
                                    else None
                                )
                        break

                if header_row_idx is None or desc_col is None or price_col is None:
                    # No recognisable header — skip this table
                    continue

                for row in table[header_row_idx + 1:]:
                    cells = [str(c or "").strip() for c in row]

                    # Left column item
                    _add_item(cells, sku_col, desc_col, pack_col, price_col, items, seen)

                    # Right column item (two-column layout)
                    if right_desc is not None and right_price is not None:
                        _add_item(cells, right_sku, right_desc, right_pack, right_price, items, seen)

    return items


def _add_item(
    cells: list[str],
    sku_col: Optional[int],
    desc_col: int,
    pack_col: Optional[int],
    price_col: int,
    items: list[PriceItem],
    seen: set[str],
) -> None:
    if desc_col >= len(cells) or price_col >= len(cells):
        return

    desc  = cells[desc_col].strip()
    if not desc or len(desc) < 2:
        return

    raw_price = cells[price_col] if price_col < len(cells) else ""
    price = _parse_price(raw_price)
    if price is None:
        return

    sku      = cells[sku_col].strip() if sku_col is not None and sku_col < len(cells) else None
    pack     = cells[pack_col].strip() if pack_col is not None and pack_col < len(cells) else None

    # Normalise: strip empty strings
    if not sku:
        sku = None
    if not pack:
        pack = None

    # Deduplicate — same SKU+desc key
    key = f"{sku or ''}|{desc.lower()}"
    if key in seen:
        return
    seen.add(key)

    needs_review = price <= 0 or price > 1_000_000

    items.append(PriceItem(
        item_name=desc,
        sku=sku,
        pack_size=pack,
        price=price,
        unit=pack,              # backwards-compat alias
        confidence=1.0 if not needs_review else 0.5,
        needs_review=needs_review,
    ))


# ── CSV extraction ─────────────────────────────────────────────────────────────

def _extract_csv(content: bytes) -> list[PriceItem]:
    import csv

    text   = content.decode("utf-8", errors="replace")
    reader = csv.reader(io.StringIO(text))
    rows   = list(reader)
    if not rows:
        return []

    header  = [h.lower().strip() for h in rows[0]]
    sku_col   = _col_index(header, _SKU_HEADERS)
    desc_col  = _col_index(header, _DESC_HEADERS)
    pack_col  = _col_index(header, _PACK_HEADERS)
    price_col = _col_index(header, _PRICE_HEADERS)

    if desc_col is None or price_col is None:
        return []

    items: list[PriceItem] = []
    seen: set[str] = set()

    for row in rows[1:]:
        cells = [str(c).strip() for c in row]
        _add_item(cells, sku_col, desc_col, pack_col, price_col, items, seen)

    return items


# ── Text extraction helper (used by assistant route) ───────────────────────────

def _extract_text_from_bytes(content: bytes, filename: str) -> str:
    """Return a plain-text preview of file contents for the AI assistant."""
    ext = filename.rsplit(".", 1)[-1].lower() if "." in filename else ""
    if ext == "pdf":
        try:
            import pdfplumber, io as _io
            with pdfplumber.open(_io.BytesIO(content)) as pdf:
                parts = []
                for page in pdf.pages[:3]:  # first 3 pages for preview
                    text = page.extract_text() or ""
                    if text:
                        parts.append(text)
                return "\n".join(parts)
        except Exception:
            return ""
    if ext in ("csv", "txt"):
        return content.decode("utf-8", errors="replace")[:3000]
    return ""


# ── Endpoint ───────────────────────────────────────────────────────────────────

@router.post("/price-extract", response_model=PriceExtractResponse)
async def price_extract(
    file: UploadFile = File(...),
    vendor_category: str = Form(...),
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> PriceExtractResponse:
    verify_jwt(credentials.credentials, os.environ["SIDECAR_JWT_SECRET"])

    content  = await file.read()
    filename = file.filename or "upload"
    ext      = filename.rsplit(".", 1)[-1].lower() if "." in filename else ""

    if ext == "pdf":
        try:
            items = _extract_pdf(content)
        except ImportError:
            items = []
    elif ext in ("csv", "txt"):
        items = _extract_csv(content)
    else:
        items = []

    if not items:
        items = [PriceItem(
            item_name="[Could not extract — please enter manually]",
            price=0.0,
            confidence=0.0,
            needs_review=True,
        )]

    flagged = sum(1 for i in items if i.needs_review)

    return PriceExtractResponse(
        items=items,
        total_items=len(items),
        flagged_items=flagged,
    )
