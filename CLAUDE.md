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

## Test Baseline

- Phase -1 baseline: 178 pass / 3 pre-existing failures (logout session, 2x Ollama-offline)
- Phase 0 baseline: 188 pass / 3 pre-existing failures (same 3, +10 new Phase 0 tests all green)
- Phase 1 baseline: target 188 pass / 3 pre-existing failures (no new test files — Phase 1 is CI tooling only)
- Phase 2 baseline: 195 pass / 3 pre-existing failures (+7 new Phase 2 tests: CircuitBreakerTest×4, AiSidecarClientTest×3)
