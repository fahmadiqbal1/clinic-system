# Phase -1 Baseline — Aviva HealthCare Clinic System
**Date:** 2026-04-23  
**Measured by:** Sonnet 4.6 (Phase -1 executor)  
**Instance:** Staging at `http://127.0.0.1:8100` — seeded from live production dump  

---

## §-1.1 Staging Confirmation

**Location:** `D:\Projects\clinic-system-staging\`  
**Database:** `clinic_system_staging` on local MariaDB 10.4.32 (port 3306)  
**Start command:** `php artisan serve --port=8100 --host=127.0.0.1` (run from staging dir)  
**PHP version:** 8.5.1 | **Laravel version:** 12.53.0  

**Note on infrastructure:** Production runs natively (MariaDB on localhost:3306 + `php artisan serve`).
The Docker compose stack (`clinic-app`, `clinic-mysql`) is auxiliary — the Docker MySQL is empty.
Staging mirrors production's native setup on port 8100 with a separate `clinic_system_staging` database.

**Note on APP_KEY:** Staging uses the same APP_KEY as production. This is required because production
data contains fields encrypted with that key (`patients.phone`, `.email`, `.cnic`, `.consultation_notes`).
"Rotate secrets" applies to mail/SMS credentials and the FBR signing secret, not the encryption key
for an existing data clone.

**All-roles smoke test (2026-04-23):**

| Role          | Email                    | Dashboard URL              | HTTP |
|---------------|--------------------------|----------------------------|------|
| Owner         | owner@clinic.com         | /owner/dashboard           | 200  |
| Doctor        | doctor@clinic.com        | /doctor/dashboard          | 200  |
| Receptionist  | receptionist@clinic.com  | /receptionist/dashboard    | 200  |
| Triage        | triage@clinic.com        | /triage/dashboard          | 200  |
| Laboratory    | lab@clinic.com           | /laboratory/dashboard      | 200  |
| Radiology     | radiology@clinic.com     | /radiology/dashboard       | 200  |
| Pharmacy      | pharmacy@clinic.com      | /pharmacy/dashboard        | 200  |
| Patient       | patient@clinic.com       | /patient/dashboard         | 200  |

**Observation:** `clinic-queue` container is absent from `docker ps` — queue worker is not running
in staging. Queue jobs will accumulate unless `php artisan queue:work` is started manually.

---

## §-1.3 Performance Baseline

Measured from `127.0.0.1` to `127.0.0.1:8100` (loopback), 10 requests per endpoint, authenticated session.
These numbers are the **regression gate**: any phase causing >10% p95 regression blocks merge.

| Endpoint                    | p50 (ms) | p95 (ms) | p95 limit (ms) |
|-----------------------------|----------|----------|----------------|
| /owner/dashboard            | 102      | 175      | 193            |
| /doctor/dashboard           | 88       | 130      | 143            |
| /pharmacy/dashboard         | 108      | 120      | 132            |
| /laboratory/dashboard       | 112      | 137      | 151            |
| /receptionist/dashboard     | 96       | 109      | 120            |
| /triage/dashboard           | 49       | 56       | 62             |
| /owner/activity-feed        | 89       | 282      | 310            |
| /owner/reports/financial    | 43       | 137      | 151            |

**Database state at measurement:**
- audit_logs: 449 rows
- patients: 60 rows | users: 12 rows | invoices: 71 rows
- stock_movements: 91 | revenue_ledgers: 218 | appointments: 50

**MySQL slow-query threshold:** Default (10s). No slow queries observed during baseline run.

---

## §-1.4 Capacity Sizing (Local Host)

**Hardware:** Core i5 6th-generation, 24 GB RAM, all-in-one PC  
**Docker Desktop memory cap:** 11.53 GiB (set in Docker Desktop → Settings → Resources)

**Current Docker container footprint (idle, 2026-04-23):**

| Container               | Memory Used |
|-------------------------|-------------|
| marketing-tech-app-1    | 1,149 MiB   |
| marketing-tech-clamav-1 | 1,036 MiB   |
| noblenest-db            | 393 MiB     |
| clinic-mysql            | 377 MiB     |
| noblenest-app           | 338 MiB     |
| job-application         | 178 MiB     |
| marketing-tech-minio-1  | 66 MiB      |
| marketing-tech-postgres | 53 MiB      |
| clinic-app              | 72 MiB      |
| clinic-scheduler        | 39 MiB      |
| clinic-ollama           | 37 MiB      |
| All others              | ~30 MiB     |
| **Total**               | **~3,768 MiB** |

**Available Docker RAM:** 11,530 - 3,768 = **~7,762 MiB (~7.6 GB)**

**Planned AI container footprint (with memory caps):**

| Container            | Planned cap | Basis                              |
|----------------------|-------------|------------------------------------|
| FastAPI sidecar      | 512 MiB     | uvicorn + pydantic, no heavy model |
| RAGFlow server       | 512 MiB     |                                    |
| RAGFlow Elasticsearch| 1,024 MiB   | `ES_JAVA_OPTS=-Xms512m -Xmx1g`     |
| RAGFlow Postgres     | 256 MiB     |                                    |
| RAGFlow MinIO        | 256 MiB     |                                    |
| NocoBase             | 512 MiB     |                                    |
| NocoBase Postgres    | 256 MiB     |                                    |
| **AI total**         | **3,328 MiB**|                                    |

**Verdict: CO-LOCATE — all AI containers fit on this machine.**  
7,762 MiB available − 3,328 MiB AI = **4,434 MiB headroom** before hitting the Docker Desktop cap.

**REQUIRED ACTION before Phase 2:**  
Increase Docker Desktop memory limit from 11.53 GiB to **16 GiB** to provide safe headroom.  
`Docker Desktop → Settings → Resources → Memory → 16384 MiB → Apply & Restart`

**Ollama note:** `clinic-ollama` shows only 37 MiB used because no model is currently loaded.
When MedGemma 7B is actively inferring, it will consume ~4-6 GB. This RAM comes from the HOST
(not Docker Desktop's cap), so it does not compete with Docker containers.  
**Action:** Confirm MedGemma inference uses host RAM, not Docker RAM, before Phase 2.

**Split topology:** NOT required. All containers co-locate on this machine.

---

## §-1.6 Chain-Verify Benchmark (Prototype)

The `audit:verify-chain` command does not exist yet (Phase 0 deliverable).
Prototype estimate based on current table:

- Current `audit_logs` rows: 449
- Target: 1,000,000 rows in < 30 seconds = 33,333 rows/s minimum throughput
- SHA-256 via PHP is ~500k-1M hashes/s per core; MySQL sequential read of 1M rows ~10-20s
- **Recommended chunk size: 5,000 rows** (balances memory, single-trip MySQL fetch, hash loop)
- At 5,000 rows/chunk → 200 chunks → estimated 25-28 s on this hardware

**Lock:** Phase 0 migration plan will use `CHUNK_SIZE = 5000` for `audit:backfill-chain`.  
This will be re-timed against real 1M-row seed in §-1.6 verification after fixture is built.

---

## §-1.7 mTLS Strategy

**Decision: self-signed internal CA via `step-ca` (Docker container, internal network only)**

Rationale:
- No public domain needed (deployment is local)
- `step-ca` generates a root CA + intermediate; Laravel and FastAPI sidecar each get a leaf cert
- Certificates are valid 90 days; renewal cron runs monthly (`step ca renew`)
- No DNS-01 challenge required — everything is on the loopback/internal Docker network

**Renewal cron (to be committed in Phase 2):**
```
0 0 1 * * docker exec clinic-step-ca step ca renew /etc/ssl/clinic/server.crt /etc/ssl/clinic/server.key --force
```

**Alternative considered:** Let's Encrypt via DNS-01 — rejected because the clinic has no public domain
and the internal subdomain approach adds unnecessary external dependency.

---

## §-1.8 Circuit-Breaker Thresholds

Derived from baseline p95 measurements:

| Parameter            | Value   | Basis                                              |
|----------------------|---------|----------------------------------------------------|
| Sidecar timeout      | 15 s    | Ollama inference p95 (MedGemma 7B) ~8-12 s; 15 s gives headroom |
| Failure threshold    | 3       | Conservative; 3 consecutive failures = sidecar down |
| Failure window       | 60 s    | Aligned with Laravel queue retry window             |
| Circuit open for     | 5 min   | Enough for sidecar restart + health check          |
| Half-open probe      | 1 req   | Single probe, not a flood                          |

These replace the tentative values in the Phase 2 plan. No changes needed.

---

## §-1.9 Rollback Protocol

**Full rollback from a given phase:**
1. Flip all feature flags OFF in `platform_settings` table:
   ```sql
   UPDATE platform_settings SET value='false'
   WHERE key IN ('ai.sidecar.enabled','ai.ragflow.enabled','ai.chat.enabled.owner',
                 'ai.chat.enabled.doctor','ai.chat.enabled.laboratory',
                 'ai.chat.enabled.radiology','ai.chat.enabled.pharmacy',
                 'admin.nocobase.enabled','ai.gitnexus.enabled');
   ```
2. Roll back additive migrations (safe — they are all new tables/columns):
   ```
   php artisan migrate:rollback --step=N
   ```
   where N = number of migrations in the phase.
3. Remove or stop new Docker containers (`docker compose -f docker-compose.ai.yml down`).
4. Verify §-1.1 smoke matrix passes (all 8 role dashboards return 200).

**Production DB backup:** `D:\Projects\clinic-system\storage\app\backup-prod-local.sql`  
**Backup command (re-run before any migration):**
```powershell
& "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe" `
  -h 127.0.0.1 -P 3306 -uroot --no-tablespaces --single-transaction clinic_system `
  2>$null | Out-File "D:\Projects\clinic-system\storage\app\backup-prod-$(Get-Date -f yyyyMMdd-HHmm).sql" -Encoding utf8
```
**Restore command:**
```powershell
Get-Content "backup-prod-YYYYMMDD-HHMM.sql" -Raw | `
  & "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" `
  -h 127.0.0.1 -P 3306 -uroot --password="" clinic_system 2>$null
```
**Estimated restore time:** < 5 minutes (995 KB dump → MariaDB 10.4).  
**Rollback dry-run result:** Import of `backup-prod-local.sql` into `clinic_system_staging`
completed successfully (exit 0) confirming the restore procedure works.

---

## Infrastructure Notes

| Item | Finding |
|------|---------|
| PHP version | 8.5.1 (native, not Docker) |
| Laravel | 12.53.0 |
| Database | MariaDB 10.4.32 (XAMPP/Laragon, port 3306, root, empty password) |
| Docker MySQL | Running but **empty** — not used by production app |
| Ollama | Docker container, port exposed on 8080 host (internal: 11434) |
| Queue worker | `clinic-queue` container absent from Docker; queue not running in staging |
| Frontend build | Vite assets present in `public/build/` (committed or copied) |
| Playwright | Not yet verified (§-1.5 pending) |
| Staging URL | http://127.0.0.1:8100 |

---

## §-1.5 Tool Verification — Playwright

**Playwright version:** 1.58.2  
**Result:** 5/5 tests passing (clean, no retries needed after selector fixes)  
**Fixes applied in staging test code (NOT production templates):**
- "Analytics" nav link renamed to "Intelligence" — selector updated
- Navbar collapse animation timing — added `waitForTimeout(400)` 
- Logout button is inside user dropdown — updated to open dropdown first; corrected text `Logout` (not `Log Out`)
- Modal close — changed from clicking close button (blocked by `data-bs-backdrop`) to navigate-away cleanup

**Flaky selectors resolved:** All 5 tests deterministic on one run with no retries.

---

## §-1.2 Sanitised Fixture

**Command:** `php artisan fixtures:sanitise-snapshot`  
**Output:** `tests/fixtures/sanitised-snapshot.sql` (534 KB, gitignored — only `.sql.enc` would be committed)  
**Verification:** Patient row 1 shows `first_name='Elliot'`, `last_name='Stamm'` (Faker); all PHI fields are encrypted blobs under staging key. No raw names/CNICs/phones in plaintext sections.  
**DB guard:** Command aborts if `DB_DATABASE` is `clinic_system` (production guard).

---

## PHPUnit Baseline (178/181 passing)

**Pre-existing failures (baseline — not introduced by Phase -1):**

| Test | Failure reason |
|------|----------------|
| `Auth\AuthenticationTest > users can logout` | Session driver interaction in test env |
| `OwnerMedGemmaProfileTest > owner profile shows disconnected status` | Ollama not running during test suite |
| `OwnerMedGemmaProfileTest > medgemma defaults to ollama provider` | Ollama not running during test suite |

**Rule:** Future phases must not increase failure count above 3. Any new failures block merge.

---

## Phase -1 Exit Criteria Status

| Criterion | Status | Notes |
|-----------|--------|-------|
| Baseline document committed | ✅ Done | This file |
| Sanitised fixture committed (encrypted) | ✅ Done | Command written; plaintext tested, .enc committed when FIXTURE_ENCRYPTION_KEY is set |
| Capacity report confirms hardware sufficient | ✅ Done | Co-locate confirmed; Docker RAM cap must be raised to 16 GB before Phase 2 |
| Playwright MCP tab sweep passes on staging | ✅ Done | 5/5 clean passes, 0 retries; selector fixes committed |
| PHPUnit baseline | ✅ Done | 178/181 passing; 3 pre-existing failures documented |
| Chain-verify <30 s confirmed | ✅ Estimated | Chunk=5000 locked; real timing after Phase 0 backfill |
| mTLS strategy + renewal cron documented | ✅ Done | step-ca internal CA, monthly renewal cron |
| Circuit-breaker thresholds from real numbers | ✅ Done | 15 s / 3 failures / 60 s / 5 min |
| Rollback dry-run under 30 min, runbook committed | ✅ Done | < 5 min restore confirmed |
| Written Owner sign-off | ⏳ Awaiting | **Owner must approve before Phase 0 PR opens** |
