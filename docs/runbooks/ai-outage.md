# AI Outage Runbook — Aviva HealthCare Clinic

**Scope:** What the Owner does when the AI sidecar, RAGFlow, or NocoBase is down.
All clinical workflows (Doctor, Triage, Lab, Radiology, Pharmacy, Receptionist) continue
without any AI component. This runbook covers AI-surface degradation only.

---

## 1. Sidecar is down (`clinic-sidecar` container)

**Symptoms:**
- AI Knowledge Assistant panels show "AI assistant temporarily unavailable."
- Owner AI & Infrastructure card shows sidecar status as red/unreachable.
- `POST /ai-assistant/query` returns `503 JSON` (not 500, not an exception page).

**Impact:** Zero impact on clinical core. Doctor consultations, invoices, prescriptions,
stock management, and triage all work normally. MedGemma direct path is preserved behind
flag if parity testing is needed.

**Recovery steps:**

```bash
# 1. Check container status
docker compose -f docker-compose.yml -f docker-compose.ai.yml ps sidecar

# 2. View recent logs
docker compose -f docker-compose.yml -f docker-compose.ai.yml logs --tail=100 sidecar

# 3. Restart the container
docker compose -f docker-compose.yml -f docker-compose.ai.yml restart sidecar

# 4. Verify health endpoint (should return {"status":"ok"})
curl http://localhost:8001/health
```

**If restart doesn't fix it:**
- Check `CLINIC_SIDECAR_JWT_SECRET` is set in `.env`.
- Check `CLINIC_SIDECAR_URL` points to `http://localhost:8001` (native) or `http://sidecar:8001` (Docker).
- Check Ollama is reachable: `curl http://localhost:11434/api/tags`.
- Check available RAM: `docker stats --no-stream`.

**Circuit breaker note:** After 3 consecutive failures within 60 s, Laravel's circuit breaker
opens for 5 minutes. All `/ai-assistant/query` calls return `503` immediately (no hang).
The breaker resets automatically after 5 min once the sidecar is back.

---

## 2. RAGFlow is down (`clinic-ragflow` container)

**Symptoms:**
- Knowledge Assistant shows "AI assistant temporarily unavailable."
- Sidecar `/v1/rag/query` returns `503`.
- RAGFlow admin UI at http://localhost:8080 is unreachable.

**Impact:** Zero impact on clinical core. The sidecar degrades gracefully; RAG queries fail
closed. MedGemma consultation analysis (non-RAG path) continues if `ai.sidecar.enabled` is ON.

**Recovery steps:**

```bash
# 1. Check which RAGFlow-stack containers are down
docker compose -f docker-compose.yml -f docker-compose.ai.yml ps

# 2. View RAGFlow logs
docker compose -f docker-compose.yml -f docker-compose.ai.yml logs --tail=100 ragflow

# 3. Check dependencies (ES and MySQL must be healthy before ragflow starts)
docker compose -f docker-compose.yml -f docker-compose.ai.yml logs ragflow-es
docker compose -f docker-compose.yml -f docker-compose.ai.yml logs ragflow-mysql

# 4. Restart the full RAGFlow stack
docker compose -f docker-compose.yml -f docker-compose.ai.yml restart \
  ragflow ragflow-es ragflow-mysql ragflow-minio ragflow-redis

# 5. Wait ~60 s for ES to become healthy, then verify RAGFlow admin UI
curl -sf http://localhost:8080 | head -5
```

**If ES is OOM-killed:** Increase `ES_JAVA_OPTS` heap in `docker-compose.ai.yml` and ensure
Docker Desktop has ≥ 16 GB RAM allocation (Docker Desktop → Settings → Resources).

**If ragflow-mysql fails:** Check `RAGFLOW_MYSQL_PASSWORD` in `.env` matches the container.
Note: this is RAGFlow's own MySQL — it **never** touches the clinic application database.

---

## 3. NocoBase is down (`clinic-nocobase` container)

**Symptoms:**
- `/owner/nocobase` gateway page shows the flag warning but the link is greyed out.
- NocoBase admin UI at http://localhost:13000 is unreachable.
- Audit webhook at `POST /api/nocobase/audit-hook` may time out (NocoBase is the caller).

**Impact:** Zero impact on clinical core. Property/equipment records are inaccessible but
all clinical, financial, and AI data is unaffected. Webhook calls will simply stop arriving;
no audit rows are lost from the clinical side.

**Recovery steps:**

```bash
# 1. Check container status
docker compose -f docker-compose.yml -f docker-compose.admin.yml ps

# 2. View logs
docker compose -f docker-compose.yml -f docker-compose.admin.yml logs --tail=100 nocobase

# 3. Restart
docker compose -f docker-compose.yml -f docker-compose.admin.yml restart nocobase nocobase-pg

# 4. Verify UI
curl -sf http://localhost:13000 | head -5
```

**If the Postgres volume is corrupt:** Restore from the latest `docker volume` backup.
Schema can be re-imported from `nocobase/schema.json` (Settings → Import/Export in the UI).

---

## 4. Evidence export during an incident

Run the SOC 2 evidence bundle command to capture a timestamped snapshot for incident records:

```bash
php artisan soc2:evidence --from=YYYY-MM-DD --to=YYYY-MM-DD
```

The output ZIP (`storage/app/soc2/evidence_*.zip`) contains:
- `audit_logs.json` — filtered audit chain rows
- `ai_invocations.json` — filtered AI call log
- `chain_verify.json` — hash-chain integrity proof
- `feature_flags.json` — flag state at export time
- `manifest.json` — metadata

---

## 5. Disabling AI surfaces without restart

All AI panels default **OFF**. If AI surfaces are causing problems, flip the flags without
touching containers:

```sql
-- Disable all chat surfaces immediately
UPDATE platform_settings
SET meta = JSON_SET(meta, '$.value', false)
WHERE provider = 'feature_flag'
  AND platform_name IN (
    'ai.sidecar.enabled', 'ai.ragflow.enabled',
    'ai.chat.enabled.owner', 'ai.chat.enabled.doctor',
    'ai.chat.enabled.laboratory', 'ai.chat.enabled.radiology',
    'ai.chat.enabled.pharmacy'
  );
```

Or disable individually via Owner → Platform Settings in the UI.

---

## 6. Escalation

If all three sidecars are simultaneously unreachable and the above steps do not resolve
the issue within 30 minutes, fall back to the rollback runbook:
`docs/runbooks/rollback.md` — restores from the last production snapshot.
