"""
T — Tool Registry

Declarative catalogue of tools available to the agent.
Each tool has: name, description, schema, activation condition, failure mode.

Tools connect through MCP — each tool is independently swappable.
The registry resolves which tools apply to a given context before
the execution loop runs.
"""
from __future__ import annotations

import logging
from dataclasses import dataclass, field
from typing import Any, Awaitable, Callable

logger = logging.getLogger(__name__)


@dataclass
class Tool:
    name: str
    description: str
    schema: dict
    invoke_fn: Callable[[dict], Awaitable[dict]]
    should_invoke: Callable[[dict], bool] = field(default_factory=lambda: (lambda _: True))
    fail_open: bool = True  # True = degrade gracefully; False = raise on failure

    async def invoke(self, context: dict) -> dict:
        try:
            return await self.invoke_fn(context)
        except Exception as exc:
            if self.fail_open:
                logger.warning("Tool %s failed (fail-open): %s", self.name, exc)
                return {"error": str(exc), "tool": self.name}
            raise


class ToolRegistry:
    """Central registry for agent tools — the T pillar."""

    def __init__(self) -> None:
        self._tools: dict[str, Tool] = {}

    def register(self, tool: Tool) -> None:
        self._tools[tool.name] = tool
        logger.debug("ToolRegistry: registered tool '%s'", tool.name)

    def get(self, name: str) -> Tool | None:
        return self._tools.get(name)

    def resolve(self, context: dict) -> list[Tool]:
        """Return the tools that should fire for this context."""
        return [t for t in self._tools.values() if t.should_invoke(context)]

    @property
    def schemas(self) -> list[dict]:
        return [
            {"name": t.name, "description": t.description, "schema": t.schema}
            for t in self._tools.values()
        ]
