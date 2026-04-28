# Aviva HealthCare — Clinic System

Laravel 11 ERP for a single clinic. Production runs natively (XAMPP / MariaDB 10.4 on port 3306, `php artisan serve` on port 8000). Staging: `D:\Projects\clinic-system-staging\` on port 8100.

## Architecture

- **Core:** Laravel + Spatie Permission + MySQL (single DB, no multi-tenancy)
- **AI:** Ollama / MedGemma via `MedGemmaService` (pseudonymised via `CaseTokenService` as of Phase 0)
- **Audit:** `audit_logs` table with SHA-256 hash chain (Phase 0). Immutable at DB level via MySQL trigger.
- **Feature flags:** `platform_settings` rows with `provider='feature_flag'`. All AI/admin flags default `false`. Check via `PlatformSetting::isEnabled('flag.name')`.

## ETCSLV Agent Harness (Phase 7)

**Agent = Model + Harness.** Every AI consultation routes through the ETCSLV harness in `sidecar/app/agent/`.

| Pillar | File | Responsibility |
|---|---|---|
| **E** Execution Loop | `execution_loop.py` | Parallel tool pre-fetch → Ollama inference |
| **T** Tool Registry | `tool_registry.py` | Declarative tool catalogue; RAGFlow wired here |
| **C** Context Manager | `context_manager.py` | Token budget (6000t), anti-ROT, system prompt |
| **S** State Store | `state_store.py` | Per-case persistence (24h TTL); prior summary |
| **L** Lifecycle Hooks | `lifecycle_hooks.py` | Pre/post hooks; structured logging; metrics |
| **V** Verification | `verification_interface.py` | PHI scan, confidence parsing, quality gates |

**Entry point:** `sidecar/app/agent/harness.py` → `AgentHarness.run(body, session_id)`

**Key behaviours:**
- System prompt is in the `system` role (not user message) — governs model behaviour
- RAGFlow retrieves relevant guidelines before inference (fail-open)
- Real confidence derived from `## CONFIDENCE` section in model output (not hardcoded)
- Real `retrieval_citations` from RAGFlow (not always `[]`)
- Prior consultation summary injected at top of context (S → C anti-ROT)
- PHI gate: if CNIC/phone detected in output, confidence=0 and output is quarantined

**MCP server:** `sidecar/mcp_server.py` — exposes tools to Claude Code:
- `sidecar_health`, `sidecar_metrics`, `toggle_feature_flag`, `verify_audit_chain`, `run_forecast`, `queue_status`
- Registered in `.claude/settings.json` as `clinic-sidecar`

**Slash commands:** `.claude/commands/` — `/toggle-flag`, `/verify-chain`, `/sidecar-health`, `/run-forecast`

## Environment Variables (Phase 0 additions)

```
CLINIC_CASE_TOKEN_SECRET=   # HMAC secret for patient pseudonyms — generate: php -r "echo base64_encode(random_bytes(32));"
CLINIC_RO_PASSWORD=         # Password for the clinic_ro read-only MySQL user
```

## Custom Artisan Commands

| Command | Description |
|---|---|
| `php artisan fixtures:sanitise-snapshot` | Generate sanitised fixture from production snapshot (Phase -1) |
| `php artisan audit:backfill-chain [--chunk=500]` | Backfill SHA-256 hash chain for existing audit_log rows. Run BEFORE installing immutability trigger. |
| `php artisan audit:verify-chain [--chunk=500]` | Verify audit_logs hash chain integrity. Exits non-zero if tampered. Run after every deploy. |
| `php artisan gitnexus:scan [--force]` | Index codebase with GitNexus and emit `storage/gitnexus/graph.json`. Run after significant refactors. (Phase 1) |
| `php artisan gitnexus:impact <symbol> [--depth=3]` | Print blast-radius report for a class or file. Paste output in every PR that touches listed callers. (Phase 1) |
| `php artisan ragflow:sync [--dry-run]` | Sync service_catalog + inventory_items (non-financial columns only) to RAGFlow via sidecar. Runs nightly at 03:00. (Phase 3) |
| `php artisan soc2:evidence [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]` | Export SOC 2 evidence bundle (audit chain + AI invocations + chain-verify proof + flag snapshot) to `storage/app/soc2/`. Omit `--from` for full history. (Phase 5) |

## Phase 0 Migration Order

Run `php artisan migrate` — all Phase 0 migrations run in sequence:

1. `000001` — adds `prev_hash`, `row_hash`, `user_agent`, `session_id` to `audit_logs`
2. `000002` — backfills hash chain for existing rows, then installs UPDATE/DELETE trigger
3. `000003` — creates `case_tokens` table
4. `000004` — creates `ai_action_requests` table
5. `000005` — seeds AI feature flags into `platform_settings` (all default OFF)
6. `000006` — creates `clinic_ro` read-only MySQL user (requires `CLINIC_RO_PASSWORD` in .env)

## Shielded Files (do not modify without Opus re-planning)

- `app/Support/Roles.php`
- `app/Support/DepartmentScope.php`
- `app/Policies/*`
- `app/Models/Invoice.php`, `app/Enums/InvoiceStatus.php`
- `app/Services/AuditableService.php`
- `app/Services/FbrService.php`

## Phase 1 Notes (GitNexus)

- GitNexus 1.5.3 is globally installed (`gitnexus --version`). No composer/npm dep added.
- `.gitnexus/` index, `storage/gitnexus/`, and `tools/gitnexus/` are all gitignored (artifacts, not source).
- Run `php artisan gitnexus:scan` to (re)generate the graph. Enable `ai.gitnexus.enabled` flag to see `/owner/architecture`.
- Feature flag `ai.gitnexus.enabled` was seeded in Phase 0 migration 000005 (default OFF).

## Phase 2 Notes (Python AI Sidecar)

- **Sidecar:** `sidecar/` — FastAPI 0.115 + Pydantic v2. Run with `docker compose -f docker-compose.yml -f docker-compose.ai.yml up -d`.
- **`AiSidecarClient`** (`app/Services/AiSidecarClient.php`) — circuit breaker (3 failures/60s → open 5 min), 15s timeout, 1 retry. Logs every call to `ai_invocations` (hash-chained).
- **`AiInvocation`** model (`app/Models/AiInvocation.php`) — SHA-256 hash chain identical to `audit_logs`.
- **Feature flag:** `ai.sidecar.enabled` (seeded OFF in Phase 0 migration 000005). When ON, `AnalyseConsultationJob` routes consultation analyses through sidecar `/v1/consult` → Ollama. Direct MedGemma path preserved for parity.
- **New env vars:** `CLINIC_SIDECAR_URL`, `CLINIC_SIDECAR_JWT_SECRET` (see `.env.example`).
- **Python tests:** `cd sidecar && pip install -r requirements.txt && pytest` — 3 test modules (health, schemas, auth).
- **Phase 2 migration:** `000007` — creates `ai_invocations` table.

## Environment Variables (Phase 2 additions)

```
CLINIC_SIDECAR_URL=http://localhost:8001   # FastAPI sidecar URL (native) / http://sidecar:8001 (Docker)
CLINIC_SIDECAR_JWT_SECRET=                 # HS256 JWT secret — generate: php -r "echo base64_encode(random_bytes(32));"
```

## Phase 3 Notes (RAGFlow + Chat Surfaces)

- **RAGFlow stack:** added to `docker-compose.ai.yml` — `ragflow` + `ragflow-es` + `ragflow-minio` + `ragflow-mysql` + `ragflow-redis`. Start with `docker compose -f docker-compose.yml -f docker-compose.ai.yml up -d`.
- **RAGFlow admin UI:** http://localhost:8080 (bound to 127.0.0.1 only). Owner creates datasets and API key in the UI after first boot. Copy key into `.env` as `RAGFLOW_API_KEY`.
- **Sidecar RAGFlow client:** `sidecar/app/services/ragflow.py` — reads `RAGFLOW_URL`, `RAGFLOW_API_KEY`, `RAGFLOW_DATASET_*` env vars. Degrades gracefully when unconfigured.
- **`RagflowClient` in Laravel:** calls to RAGFlow go through `AiSidecarClient::ragQuery()` / `ragIngestContent()` — same circuit-breaker + hash-chain logging path as Phase 2.
- **`AiOversightController`:** Owner-only page at `/owner/ai-oversight` — sidecar health ping, RAGFlow flag status, pending `ai_action_requests`, top cited docs (7d), recent invocations.
- **`AiAssistantController`:** AJAX at `POST /ai-assistant/query` + `POST /ai-assistant/flag`. Flag check: `ai.chat.enabled.{role}` (all default OFF). Returns 503 JSON on sidecar outage — never 500.
- **`ai-assistant-panel` component:** included on doctor consultation view (flag `ai.chat.enabled.doctor`). Collection defaults to `service_catalog` for Doctor, `inventory` for Pharmacy.
- **Owner dashboard:** AI & Infrastructure card injected when `ai.sidecar.enabled` OR `ai.ragflow.enabled` is ON. No change to existing metrics.
- **Feature flags (new):** no new seeded flags — Phase 0 already seeded `ai.chat.enabled.{owner,doctor,laboratory,radiology,pharmacy}` (all OFF).
- **Phase 3 deviation:** Opus plan specified Postgres for RAGFlow internals; RAGFlow 0.14 uses MySQL. `ragflow-mysql` is used instead. Isolated volume, never touches app MySQL.
- **New env vars:**
  ```
  RAGFLOW_API_KEY=               # Generated in RAGFlow admin UI after first boot
  RAGFLOW_DATASET_GENERAL=       # Dataset ID from RAGFlow UI (default: "general")
  RAGFLOW_DATASET_CATALOG=       # Dataset ID for service catalog corpus
  RAGFLOW_DATASET_INVENTORY=     # Dataset ID for inventory corpus
  RAGFLOW_MINIO_USER=ragflow     # MinIO root user for RAGFlow (default: ragflow)
  RAGFLOW_MINIO_PASSWORD=        # MinIO password for RAGFlow
  RAGFLOW_MYSQL_ROOT_PASSWORD=   # Root password for ragflow-mysql container
  RAGFLOW_MYSQL_PASSWORD=        # ragflow user password for ragflow-mysql
  ```

## Phase 4 Notes (NocoBase property & equipment admin)

- **NocoBase stack:** added to `docker-compose.admin.yml` — `nocobase` (latest) + `nocobase-pg` (Postgres 15). Start with `docker compose -f docker-compose.yml -f docker-compose.admin.yml up -d`.
- **NocoBase UI:** http://localhost:13000 (bound to 127.0.0.1 only). Owner creates schema via UI; import seed schema from `nocobase/schema.json` (Settings → Import/Export).
- **Owner gateway:** `GET /owner/nocobase` — Spatie `role:Owner` enforced at route; shows link to NocoBase UI. Flag: `admin.nocobase.enabled` (seeded OFF in Phase 0 migration 000005).
- **Audit webhook:** NocoBase POSTs to `POST /api/nocobase/audit-hook`. HMAC-SHA256 via `X-NocoBase-Signature: sha256=<hex>`. Controller verifies against `CLINIC_NOCOBASE_WEBHOOK_SECRET`. Writes to `audit_logs` as `action = 'nocobase.<table>.<event>'`, `auditable_type = 'Nocobase'`.
- **SSO decision:** NocoBase auth plugin does not support signed-cookie handoff from PHP sessions. Fallback: separate Owner-only NocoBase login (UI only reachable at 127.0.0.1:13000; not internet-exposed).
- **Schema tables:** `properties` (lease), `equipment` (serial/warranty/vendor), `service_history` (per-equipment), `vendors` (contacts/contracts). Schema export: `nocobase/schema.json`.
- **New env vars:**
  ```
  CLINIC_NOCOBASE_WEBHOOK_SECRET=   # HMAC secret — generate: php -r "echo base64_encode(random_bytes(32));"
  NOCOBASE_DB_PASSWORD=             # Postgres password for nocobase container
  NOCOBASE_APP_KEY=                 # NocoBase app key — generate: php -r "echo base64_encode(random_bytes(32));"
  NOCOBASE_URL=http://localhost:13000  # URL of NocoBase UI (displayed in owner gateway page)
  ```

## Phase 5 Notes (Observability + Evidence Export)

- **`soc2:evidence`:** Exports audit_logs + ai_invocations (date-filtered) + chain-verify proof + feature-flag snapshot into a ZIP at `storage/app/soc2/`. Suitable for SOC 2 auditors.
- **Retention Policy:** Owner UI at `GET /owner/retention-policy`. Settings stored in `platform_settings` rows with `provider='retention_policy'` (keys: `retention.clinical`, `retention.financial`, `retention.ai`). Default: clinical=indefinite, financial=7y, AI=2y. Advisory only — no automated purge in v1.
- **Prometheus `/metrics`:** Exposed by the Python sidecar at `GET /metrics` (no auth). Tracks `sidecar_requests_total` and `sidecar_request_duration_seconds` per endpoint.
- **Grafana (optional):** Added to `docker-compose.ai.yml`. Start with `docker compose -f docker-compose.yml -f docker-compose.ai.yml up -d prometheus grafana`. UI at 127.0.0.1:3000. Pre-provisioned dashboard: `sidecar/observability/grafana/dashboards/clinic-sidecar.json`.
- **Outage runbook:** `docs/runbooks/ai-outage.md` — steps for sidecar / RAGFlow / NocoBase outage recovery, flag-toggle quick-disable, and evidence export during incident.
- **New env vars:**
  ```
  CLINIC_GRAFANA_URL=http://localhost:3000   # URL of Grafana UI (optional, display only)
  GRAFANA_ADMIN_PASSWORD=                    # Grafana admin password for observability stack
  ```

## Phase 6 Notes (Forecasting + Stress/Regression + Cutover)

- **Revenue forecast sidecar (`/v1/forecast/revenue`):** Queries `audit_logs` (via `clinic_ro`) for 90-day event counts grouped by day, applies single exponential smoothing (α=0.3), projects `days_ahead` forward. Model ID: `revenue-ses-v1`.
- **Inventory forecast sidecar (`/v1/forecast/inventory`):** Queries `inventory_items` (non-financial columns via `clinic_ro`) and classifies each active item as `critical` (qty=0), `warning` (qty ≤ min_stock_level), or `ok`. Model ID: `inventory-threshold-v1`. Both endpoints degrade gracefully (empty `forecast: []`) when `CLINIC_RO_*` env vars are not set.
- **DB service:** `sidecar/app/services/db.py` — async `aiomysql` connection pool. Lazy-init; never throws at import time.
- **Laravel tests:** `tests/Feature/Ai/ForecastTest.php` ×5.
- **Sidecar tests:** `sidecar/tests/test_forecast.py` ×5.
- **Load test:** `tests/load/k6-load.js` — E.3 gate (50 VUs, 5 min, p95 < 800 ms, errors < 1%). Run instructions in file header.
- **Tab sweep:** `tests/e2e/tab-sweep.spec.js` — E.4 gate, all 8 roles, all nav links, screenshots to `tests/e2e/screenshots/<role>/`.
- **Cutover runbook:** `docs/runbooks/cutover.md` — Part G steps as an executable checklist. Run on staging first, then production.
- **New env vars (sidecar only — passed via docker-compose.ai.yml):**
  ```
  # DB_HOST / DB_PORT / DB_DATABASE already exist in .env — no new vars needed in native mode.
  # Docker: CLINIC_RO_HOST=host.docker.internal injected automatically from docker-compose.ai.yml.
  ```

## Test Baseline

- Phase -1 baseline: 178 pass / 3 pre-existing failures (logout session, 2x Ollama-offline)
- Phase 0 baseline: 188 pass / 3 pre-existing failures (same 3, +10 new Phase 0 tests all green)
- Phase 1 baseline: target 188 pass / 3 pre-existing failures (no new test files — Phase 1 is CI tooling only)
- Phase 2 baseline: 195 pass / 3 pre-existing failures (+7 new Phase 2 tests: CircuitBreakerTest×4, AiSidecarClientTest×3)
- Phase 3 baseline: target 202 pass / 3 pre-existing failures (+7 new Phase 3 tests: RagflowOutageTest×4, RagflowSyncPhiTest×3)
- Phase 4 baseline: target 207 pass / 3 pre-existing failures (+5 new Phase 4 tests: NocobaseAuditHookTest×5)
- Phase 5 baseline: target 212 pass / 3 pre-existing failures (+5 new Phase 5 tests: Soc2EvidenceTest×5)
- Phase 6 baseline: 217 pass / 3 pre-existing failures (+5 new Phase 6 tests: ForecastTest×5)

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **clinic-system** (3702 symbols, 9166 relationships, 279 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## When Debugging

1. `gitnexus_query({query: "<error or symptom>"})` — find execution flows related to the issue
2. `gitnexus_context({name: "<suspect function>"})` — see all callers, callees, and process participation
3. `READ gitnexus://repo/clinic-system/process/{processName}` — trace the full execution flow step by step
4. For regressions: `gitnexus_detect_changes({scope: "compare", base_ref: "main"})` — see what your branch changed

## When Refactoring

- **Renaming**: MUST use `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` first. Review the preview — graph edits are safe, text_search edits need manual review. Then run with `dry_run: false`.
- **Extracting/Splitting**: MUST run `gitnexus_context({name: "target"})` to see all incoming/outgoing refs, then `gitnexus_impact({target: "target", direction: "upstream"})` to find all external callers before moving code.
- After any refactor: run `gitnexus_detect_changes({scope: "all"})` to verify only expected files changed.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Tools Quick Reference

| Tool | When to use | Command |
|------|-------------|---------|
| `query` | Find code by concept | `gitnexus_query({query: "auth validation"})` |
| `context` | 360-degree view of one symbol | `gitnexus_context({name: "validateUser"})` |
| `impact` | Blast radius before editing | `gitnexus_impact({target: "X", direction: "upstream"})` |
| `detect_changes` | Pre-commit scope check | `gitnexus_detect_changes({scope: "staged"})` |
| `rename` | Safe multi-file rename | `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` |
| `cypher` | Custom graph queries | `gitnexus_cypher({query: "MATCH ..."})` |

## Impact Risk Levels

| Depth | Meaning | Action |
|-------|---------|--------|
| d=1 | WILL BREAK — direct callers/importers | MUST update these |
| d=2 | LIKELY AFFECTED — indirect deps | Should test |
| d=3 | MAY NEED TESTING — transitive | Test if critical path |

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/clinic-system/context` | Codebase overview, check index freshness |
| `gitnexus://repo/clinic-system/clusters` | All functional areas |
| `gitnexus://repo/clinic-system/processes` | All execution flows |
| `gitnexus://repo/clinic-system/process/{name}` | Step-by-step execution trace |

## Self-Check Before Finishing

Before completing any code modification task, verify:
1. `gitnexus_impact` was run for all modified symbols
2. No HIGH/CRITICAL risk warnings were ignored
3. `gitnexus_detect_changes()` confirms changes match expected scope
4. All d=1 (WILL BREAK) dependents were updated

## Keeping the Index Fresh

After committing code changes, the GitNexus index becomes stale. Re-run analyze to update it:

```bash
npx gitnexus analyze
```

If the index previously included embeddings, preserve them by adding `--embeddings`:

```bash
npx gitnexus analyze --embeddings
```

To check whether embeddings exist, inspect `.gitnexus/meta.json` — the `stats.embeddings` field shows the count (0 means no embeddings). **Running analyze without `--embeddings` will delete any previously generated embeddings.**

> Claude Code users: A PostToolUse hook handles this automatically after `git commit` and `git merge`.

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
