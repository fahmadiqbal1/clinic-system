# Aviva HealthCare — Clinic System

Laravel 11 ERP for a single clinic. Production runs natively (XAMPP / MariaDB 10.4 on port 3306, `php artisan serve` on port 8000). Staging: `D:\Projects\clinic-system-staging\` on port 8100.

## Architecture

- **Core:** Laravel + Spatie Permission + MySQL (single DB, no multi-tenancy)
- **AI:** Ollama / MedGemma via `MedGemmaService` (pseudonymised via `CaseTokenService` as of Phase 0)
- **Audit:** `audit_logs` table with SHA-256 hash chain (Phase 0). Immutable at DB level via MySQL trigger.
- **Feature flags:** `platform_settings` rows with `provider='feature_flag'`. All AI/admin flags default `false`. Check via `PlatformSetting::isEnabled('flag.name')`.

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

## Test Baseline

- Phase -1 baseline: 178 pass / 3 pre-existing failures (logout session, 2x Ollama-offline)
- Phase 0 baseline: 188 pass / 3 pre-existing failures (same 3, +10 new Phase 0 tests all green)
- Phase 1 baseline: target 188 pass / 3 pre-existing failures (no new test files — Phase 1 is CI tooling only)
- Phase 2 baseline: 195 pass / 3 pre-existing failures (+7 new Phase 2 tests: CircuitBreakerTest×4, AiSidecarClientTest×3)
- Phase 3 baseline: target 202 pass / 3 pre-existing failures (+7 new Phase 3 tests: RagflowOutageTest×4, RagflowSyncPhiTest×3)
- Phase 4 baseline: target 207 pass / 3 pre-existing failures (+5 new Phase 4 tests: NocobaseAuditHookTest×5)
