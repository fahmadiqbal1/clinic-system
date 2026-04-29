"""
Aviva Clinic AI Sidecar — MCP Server

Exposes sidecar and clinic tools to Claude Code via the Model Context Protocol.
Claude uses these tools to inspect AI health, run forecasts, toggle flags, and
verify the audit chain — all without leaving the chat interface.

Setup (add to .claude/settings.json or global settings):
  {
    "mcpServers": {
      "clinic-sidecar": {
        "command": "python",
        "args": ["D:/Projects/clinic-system/sidecar/mcp_server.py"],
        "env": {
          "CLINIC_SIDECAR_URL": "http://localhost:8001"
        }
      }
    }
  }

Run standalone: python sidecar/mcp_server.py
"""
from __future__ import annotations

import asyncio
import json
import os
import subprocess
import sys

import httpx

try:
    from mcp.server import Server
    from mcp.server.stdio import stdio_server
    from mcp.types import (
        CallToolRequest,
        CallToolResult,
        ListToolsRequest,
        ListToolsResult,
        TextContent,
        Tool,
    )
    _MCP_AVAILABLE = True
except ImportError:
    _MCP_AVAILABLE = False


SIDECAR_URL = os.environ.get("CLINIC_SIDECAR_URL", "http://localhost:8001").rstrip("/")


# ── HTTP helpers ─────────────────────────────────────────────────────────────

async def _get(path: str, timeout: float = 10.0) -> dict:
    async with httpx.AsyncClient(timeout=timeout) as c:
        r = await c.get(f"{SIDECAR_URL}{path}")
        r.raise_for_status()
        return r.json()


async def _artisan(cmd: str) -> str:
    """Run a php artisan command in the clinic-system project root."""
    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    result = subprocess.run(
        ["php", "artisan"] + cmd.split(),
        cwd=project_root,
        capture_output=True,
        text=True,
        timeout=60,
    )
    output = result.stdout + result.stderr
    return output.strip()


# ── Tool definitions ─────────────────────────────────────────────────────────

_TOOLS = [
    {
        "name": "sidecar_health",
        "description": "Check the AI sidecar health status (no auth required).",
        "inputSchema": {"type": "object", "properties": {}, "required": []},
    },
    {
        "name": "sidecar_metrics",
        "description": "Retrieve Prometheus metrics from the AI sidecar.",
        "inputSchema": {"type": "object", "properties": {}, "required": []},
    },
    {
        "name": "toggle_feature_flag",
        "description": (
            "Enable or disable a clinic feature flag via php artisan tinker. "
            "Flag examples: ai.sidecar.enabled, ai.ragflow.enabled, ai.chat.enabled.doctor"
        ),
        "inputSchema": {
            "type": "object",
            "properties": {
                "flag": {"type": "string", "description": "Feature flag key"},
                "enabled": {"type": "boolean", "description": "True to enable, false to disable"},
            },
            "required": ["flag", "enabled"],
        },
    },
    {
        "name": "verify_audit_chain",
        "description": "Run php artisan audit:verify-chain and return the result.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "chunk": {"type": "integer", "default": 500, "description": "Rows per batch"},
            },
        },
    },
    {
        "name": "run_forecast",
        "description": "Fetch a revenue or inventory forecast from the sidecar (requires JWT).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "type": {
                    "type": "string",
                    "enum": ["revenue", "inventory"],
                    "description": "Forecast type",
                },
            },
            "required": ["type"],
        },
    },
    {
        "name": "queue_status",
        "description": "Show the Laravel queue status (failed jobs, pending jobs).",
        "inputSchema": {"type": "object", "properties": {}, "required": []},
    },
    # ── Phase 8F: ETCSLV multi-persona tools ─────────────────────────────────
    {
        "name": "admin_analyse",
        "description": (
            "Run the administrative AI persona — surfaces revenue, discount, "
            "FBR, and payout findings. Returns finding + priority + action items."
        ),
        "inputSchema": {
            "type": "object",
            "properties": {
                "session_token": {"type": "string", "description": "64-hex session token"},
                "query_type": {
                    "type": "string",
                    "enum": ["revenue_anomaly", "discount_risk", "fbr_status", "payout_audit", "general"],
                },
                "period_days": {"type": "integer", "default": 7},
                "custom_question": {"type": "string"},
            },
            "required": ["session_token"],
        },
    },
    {
        "name": "ops_analyse",
        "description": (
            "Run the operations AI persona — inventory, procurement, expense, "
            "queue health. Critical inventory items are always escalated."
        ),
        "inputSchema": {
            "type": "object",
            "properties": {
                "session_token": {"type": "string", "description": "64-hex session token"},
                "domain": {
                    "type": "string",
                    "enum": ["inventory", "procurement", "expense", "queue", "general"],
                },
                "period_days": {"type": "integer", "default": 30},
                "custom_question": {"type": "string"},
            },
            "required": ["session_token"],
        },
    },
    {
        "name": "compliance_analyse",
        "description": (
            "Run the compliance AI persona — audit chain verification, PHI "
            "access scan, evidence gaps, certification readiness."
        ),
        "inputSchema": {
            "type": "object",
            "properties": {
                "session_token": {"type": "string", "description": "64-hex session token"},
                "scope": {
                    "type": "string",
                    "enum": ["audit_chain", "phi_access", "evidence_gap", "flag_snapshot", "full"],
                },
                "period_days": {"type": "integer", "default": 30},
                "custom_question": {"type": "string"},
            },
            "required": ["session_token"],
        },
    },
    {
        "name": "etcslv_status",
        "description": (
            "Report ETCSLV pillar health for all four personas — clinical, "
            "admin, ops, compliance. Includes hook count, tool count, redis backing."
        ),
        "inputSchema": {"type": "object", "properties": {}, "required": []},
    },
]


# ── Tool handlers ─────────────────────────────────────────────────────────────

async def _handle_tool(name: str, arguments: dict) -> str:
    if name == "sidecar_health":
        try:
            data = await _get("/health")
            return json.dumps(data, indent=2)
        except Exception as exc:
            return f"Sidecar unreachable: {exc}"

    elif name == "sidecar_metrics":
        try:
            async with httpx.AsyncClient(timeout=5.0) as c:
                r = await c.get(f"{SIDECAR_URL}/metrics")
                return r.text[:3000]
        except Exception as exc:
            return f"Metrics unavailable: {exc}"

    elif name == "toggle_feature_flag":
        flag = arguments["flag"]
        value = "true" if arguments["enabled"] else "false"
        php_code = (
            f"\\App\\Models\\PlatformSetting::where('key', '{flag}')"
            f"->update(['value' => '{value}']);"
        )
        result = await asyncio.to_thread(
            _artisan, f"tinker --execute=\"{php_code}\""
        )
        return f"Flag '{flag}' set to {value}.\n{result}"

    elif name == "verify_audit_chain":
        chunk = arguments.get("chunk", 500)
        result = await asyncio.to_thread(_artisan, f"audit:verify-chain --chunk={chunk}")
        return result

    elif name == "run_forecast":
        forecast_type = arguments["type"]
        try:
            data = await _get(f"/v1/forecast/{forecast_type}")
            summary = {
                "model_id": data.get("model_id"),
                "generated_at": data.get("generated_at"),
                "items": len(data.get("forecast", [])),
                "sample": data.get("forecast", [])[:5],
            }
            return json.dumps(summary, indent=2)
        except Exception as exc:
            return f"Forecast unavailable: {exc}"

    elif name == "queue_status":
        result = await asyncio.to_thread(_artisan, "queue:monitor database")
        return result

    elif name in ("admin_analyse", "ops_analyse", "compliance_analyse"):
        # Map MCP tool name to sidecar route
        route_map = {
            "admin_analyse": "/v1/admin/analyse",
            "ops_analyse": "/v1/ops/analyse",
            "compliance_analyse": "/v1/compliance/analyse",
        }
        path = route_map[name]
        # The sidecar requires a JWT; the operator must supply it via env.
        jwt = os.environ.get("CLINIC_SIDECAR_JWT")
        if not jwt:
            return (
                "CLINIC_SIDECAR_JWT not set. Mint a JWT via Laravel "
                "AiSidecarClient::mintJwt() and export it before using this tool."
            )
        try:
            async with httpx.AsyncClient(timeout=120.0) as c:
                r = await c.post(
                    f"{SIDECAR_URL}{path}",
                    json=arguments,
                    headers={"Authorization": f"Bearer {jwt}"},
                )
                if r.status_code >= 400:
                    return f"{name} returned {r.status_code}: {r.text[:500]}"
                return json.dumps(r.json(), indent=2)
        except Exception as exc:
            return f"{name} unavailable: {exc}"

    elif name == "etcslv_status":
        # HarnessFactory.status() runs in-process when this MCP is co-located
        # with the sidecar venv; otherwise falls back to a sidecar HTTP call.
        try:
            from app.agent.harness_factory import HarnessFactory  # type: ignore
            return json.dumps(HarnessFactory.status(), indent=2)
        except Exception:
            try:
                data = await _get("/health")
                return json.dumps({
                    "note": "Status fetched via sidecar /health (factory not importable here)",
                    "sidecar": data,
                }, indent=2)
            except Exception as exc:
                return f"etcslv_status unavailable: {exc}"

    return f"Unknown tool: {name}"


# ── MCP Server entry point ────────────────────────────────────────────────────

async def run_mcp_server() -> None:
    if not _MCP_AVAILABLE:
        print("ERROR: mcp package not installed. Run: pip install mcp>=1.0.0", file=sys.stderr)
        sys.exit(1)

    server = Server("aviva-clinic-sidecar")

    @server.list_tools()
    async def list_tools(request: ListToolsRequest) -> ListToolsResult:
        return ListToolsResult(
            tools=[
                Tool(
                    name=t["name"],
                    description=t["description"],
                    inputSchema=t["inputSchema"],
                )
                for t in _TOOLS
            ]
        )

    @server.call_tool()
    async def call_tool(request: CallToolRequest) -> CallToolResult:
        result = await _handle_tool(request.params.name, request.params.arguments or {})
        return CallToolResult(content=[TextContent(type="text", text=result)])

    async with stdio_server() as (read_stream, write_stream):
        await server.run(read_stream, write_stream, server.create_initialization_options())


if __name__ == "__main__":
    asyncio.run(run_mcp_server())
