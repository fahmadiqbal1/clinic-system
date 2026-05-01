/**
 * k6 load test — Aviva Clinic System
 *
 * Validates Part E §E.3: 50 VUs, 5 min, p95 < 800 ms (non-AI), error rate < 1%.
 * Phase 8 AI endpoints get a relaxed p95 < 15 000 ms threshold (online provider latency).
 *
 * Prerequisites:
 *   1. k6 installed: https://k6.io/docs/getting-started/installation/
 *   2. App server running: php artisan serve --host=127.0.0.1 --port=8000
 *   3. Grab a valid Laravel session cookie:
 *        curl -c /tmp/clinic-cookies.txt -d "email=owner@clinic.com&password=password123" \
 *             -H "X-CSRF-Token: $(curl -s http://127.0.0.1:8000/login | grep 'csrf-token' | sed 's/.*content="\([^"]*\)".*/\1/')" \
 *             -L http://127.0.0.1:8000/login
 *        export CLINIC_SESSION=$(grep laravel_session /tmp/clinic-cookies.txt | awk '{print $NF}')
 *        export CSRF_TOKEN=$(curl -s http://127.0.0.1:8000/login | grep -oP '(?<=name="_token" value=")[^"]+')
 *   4. Run:
 *        k6 run -e BASE_URL=http://127.0.0.1:8000 \
 *               -e CLINIC_SESSION=$CLINIC_SESSION \
 *               -e CSRF_TOKEN=$CSRF_TOKEN \
 *               tests/load/k6-load.js
 *
 * Thresholds:
 *   - p95 < 800 ms for all non-AI GET endpoints (E.3 gate)
 *   - p95 < 15 000 ms for AI POST endpoints (online provider gate)
 *   - error rate (non-200/429) < 1%
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const errorRate   = new Rate('errors');
const aiLatency   = new Trend('ai_latency_ms', true);

export const options = {
    scenarios: {
        // Non-AI GET endpoints — 50 VUs steady state
        page_load: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '1m', target: 50 },
                { duration: '3m', target: 50 },
                { duration: '1m', target: 0 },
            ],
            exec: 'pageLoadScenario',
        },
        // AI POST endpoints — 5 VUs (AI is expensive, rate-limited at 20/min)
        ai_endpoints: {
            executor: 'constant-vus',
            vus: 5,
            duration: '5m',
            exec: 'aiScenario',
            startTime: '30s',  // let page load settle first
        },
    },
    thresholds: {
        'http_req_duration{scenario:page_load}':  ['p(95)<800'],
        'http_req_duration{scenario:ai_endpoints}': ['p(95)<15000'],
        errors: ['rate<0.01'],
    },
};

const BASE     = __ENV.BASE_URL    || 'http://127.0.0.1:8000';
const SESSION  = __ENV.CLINIC_SESSION || '';
const CSRF     = __ENV.CSRF_TOKEN  || 'test-csrf-token';

const PAGE_ENDPOINTS = [
    '/owner/dashboard',
    '/owner/revenue-forecast',
    '/owner/activity-feed',
    '/owner/financial-report',
    '/owner/expense-intelligence',
    '/owner/inventory-health',
    '/owner/admin-ai',
    '/owner/ops-ai',
    '/owner/compliance-ai',
];

const sessionHeaders = () => SESSION
    ? { Cookie: `laravel_session=${SESSION}` }
    : {};

const jsonHeaders = () => ({
    ...sessionHeaders(),
    'Content-Type': 'application/json',
    'Accept':       'application/json',
    'X-CSRF-TOKEN': CSRF,
});

export function pageLoadScenario() {
    for (const path of PAGE_ENDPOINTS) {
        const res = http.get(`${BASE}${path}`, {
            headers: sessionHeaders(),
            tags: { endpoint: path },
        });
        const ok = check(res, {
            [`GET ${path} → 200`]: (r) => r.status === 200,
        });
        errorRate.add(!ok);
    }
    sleep(0.5);
}

export function aiScenario() {
    const aiCalls = [
        {
            url:  `${BASE}/owner/admin-ai/analyse`,
            body: { query_type: 'revenue_anomaly', period_days: 30 },
            tag:  'admin-revenue',
        },
        {
            url:  `${BASE}/owner/admin-ai/analyse`,
            body: { query_type: 'discount_risk', period_days: 30 },
            tag:  'admin-discount',
        },
        {
            url:  `${BASE}/owner/ops-ai/analyse`,
            body: { domain: 'inventory', period_days: 30 },
            tag:  'ops-inventory',
        },
        {
            url:  `${BASE}/owner/ops-ai/analyse`,
            body: { domain: 'queue', period_days: 7 },
            tag:  'ops-queue',
        },
        {
            url:  `${BASE}/owner/compliance-ai/run`,
            body: { scope: 'flag_snapshot', period_days: 7 },
            tag:  'compliance-flags',
        },
    ];

    const call = aiCalls[Math.floor(Math.random() * aiCalls.length)];
    const start = Date.now();
    const res = http.post(call.url, JSON.stringify(call.body), {
        headers: jsonHeaders(),
        timeout: '20s',
        tags: { endpoint: call.tag },
    });
    aiLatency.add(Date.now() - start);

    // 429 = rate limited (expected under load) — not an error
    const ok = check(res, {
        [`${call.tag} → 200|429`]: (r) => r.status === 200 || r.status === 429,
    });
    if (res.status !== 429) errorRate.add(!ok);

    sleep(3);  // respect rate limit: 20 req/min = 1 req/3s per VU
}
