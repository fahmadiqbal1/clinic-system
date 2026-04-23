# Production Cutover Runbook
# Aviva HealthCare — Clinic System (Phases 0–6)

Execute these steps **in order**. Each step has a pass/fail gate. Stop and raise to Owner if any gate fails.

---

## Pre-conditions (verify before starting)

- [ ] Maintenance window agreed with Owner
- [ ] Last production backup taken and restore tested (`docs/runbooks/rollback.md`)
- [ ] Staging has run this runbook once successfully end-to-end
- [ ] All new feature flags are **OFF** in production `.env` / `platform_settings`

---

## Step 1 — Pull & verify clean state

```bash
git checkout master && git pull
git log --oneline -6          # confirm tip = Phase 6 commit
php artisan gitnexus:impact app/Models/Invoice.php   # must list callers only, no "shielded" hits
```

Gate: `gitnexus:impact` output contains no shielded files (`Roles.php`, `Policies/*`, `FbrService.php`).

---

## Step 2 — Install dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

Gate: both exit 0 with no errors.

---

## Step 3 — Run migrations

```bash
php artisan migrate --force
```

Gate: all migrations complete; no errors. If any fail, run `php artisan migrate:status` to identify the failing migration and stop.

---

## Step 4 — Verify audit chain integrity

```bash
php artisan audit:verify-chain
```

Gate: exits 0. If it fails, **do not proceed** — the audit chain is corrupt. Raise to Owner.

---

## Step 5 — Cache config/routes/views

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

Gate: all five exit 0.

---

## Step 6 — Run the full PHPUnit suite

```bash
php artisan test --stop-on-failure
```

Gate: **0 regressions** vs. Phase 6 baseline (217 pass / 3 pre-existing failures).
The 3 pre-existing failures are: logout session, 2× Ollama-offline. All others must pass.

---

## Step 7 — Start optional sidecar stack (AI features only)

```bash
docker compose -f docker-compose.yml \
               -f docker-compose.ai.yml \
               -f docker-compose.admin.yml \
               up -d
```

Gate: `docker compose ps` shows all containers `running` (healthy). Allow 60 s for RAGFlow to initialise.

Verify sidecar health:
```bash
curl -s http://127.0.0.1:8001/health | python -m json.tool
```
Expected: `{"status": "ok", "version": "2.0.0"}`.

---

## Step 8 — Run Playwright smoke suite

```bash
npx playwright test tests/e2e/bootstrap-ui.spec.js
```

Gate: all 5 specs pass.

---

## Step 9 — Run E.4 exhaustive tab sweep

```bash
npx playwright test tests/e2e/tab-sweep.spec.js
```

Gate: all roles pass. Screenshots written to `tests/e2e/screenshots/<role>/`. Present to Owner for visual sign-off.

---

## Step 10 — Run E.3 load test (optional but recommended)

Prerequisites: k6 installed. Grab a session cookie first (see `tests/load/k6-load.js` header).

```bash
k6 run \
  -e BASE_URL=http://127.0.0.1:8000 \
  -e CLINIC_SESSION=$CLINIC_SESSION \
  tests/load/k6-load.js
```

Gate: `p(95) < 800 ms`, `errors < 1%`. If thresholds fail, investigate before proceeding.

---

## Step 11 — E.5 Rollback rehearsal

Verify the system is invisible to end-users when all flags are OFF (the production default):

```bash
# Confirm all AI/admin flags are off
php artisan tinker --execute="
App\Models\PlatformSetting::where('provider','feature_flag')->get()
    ->each(fn(\$s) => print(\$s->key . '=' . \$s->value . PHP_EOL));
"
```

Expected: every `ai.*` and `admin.*` flag is `false` or `0`.

Run smoke suite again to confirm no regression:
```bash
npx playwright test tests/e2e/bootstrap-ui.spec.js
```

Gate: all 5 pass.

---

## Step 12 — Tag and obtain Owner approval

```bash
git tag v-phase6-production
```

Share the tab-sweep screenshots with Owner via the AI Oversight page (`/owner/ai-oversight`).
Owner signs off in writing (message or email) before any flag is enabled.

---

## Step 13 — Enable flags (Owner action, post-approval)

Owner navigates to `/owner/platform-settings` and enables flags **one at a time**, role by role, under observation:

| Flag | Enables |
|---|---|
| `ai.sidecar.enabled` | Routes consultations through FastAPI sidecar |
| `ai.ragflow.enabled` | RAGFlow corpus synced; AI Oversight card active |
| `ai.chat.enabled.owner` | Owner chat panel |
| `ai.chat.enabled.doctor` | Doctor Knowledge Assistant |
| `ai.chat.enabled.pharmacy` | Pharmacy drug-interaction Q&A |
| `ai.chat.enabled.laboratory` | Lab parameter explainer |
| `ai.chat.enabled.radiology` | Radiology report templates |
| `admin.nocobase.enabled` | Owner → NocoBase gateway link |
| `ai.gitnexus.enabled` | Owner → Architecture graph |

After each flag flip, refresh the affected role's dashboard and confirm no errors.

---

## Abort / rollback

If anything in Steps 6–13 fails:

1. Flip all `ai.*` and `admin.*` flags to `false` via Platform Settings.
2. `docker compose -f docker-compose.ai.yml down` (sidecars only — never the main app).
3. If a migration needs rolling back: `php artisan migrate:rollback --step=N`.
4. Restore from the pre-cutover backup if state is uncertain.
5. Re-run `php artisan audit:verify-chain` to confirm chain integrity.

Full rollback procedure: `docs/runbooks/rollback.md`.
