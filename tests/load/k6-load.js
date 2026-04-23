/**
 * k6 load test — Aviva Clinic System
 *
 * Validates Part E §E.3: 50 VUs, 5 min, p95 < 800 ms, error rate < 1%.
 *
 * Prerequisites:
 *   1. k6 installed: https://k6.io/docs/getting-started/installation/
 *   2. App server running: php artisan serve --host=127.0.0.1 --port=8000
 *   3. Grab a valid Laravel session cookie:
 *        curl -c /tmp/clinic-cookies.txt -d "email=owner@clinic.com&password=password123" \
 *             -H "X-CSRF-Token: $(curl -s http://127.0.0.1:8000/login | grep 'csrf-token' | sed 's/.*content="\([^"]*\)".*/\1/')" \
 *             -L http://127.0.0.1:8000/login
 *        export CLINIC_SESSION=$(grep laravel_session /tmp/clinic-cookies.txt | awk '{print $NF}')
 *   4. Run:
 *        k6 run -e BASE_URL=http://127.0.0.1:8000 -e CLINIC_SESSION=$CLINIC_SESSION tests/load/k6-load.js
 *
 * Thresholds (E.3 gates):
 *   - p95 request duration < 800 ms
 *   - error rate (non-200) < 1%
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
    scenarios: {
        load: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '1m', target: 50 },   // ramp up
                { duration: '3m', target: 50 },   // steady state
                { duration: '1m', target: 0 },    // ramp down
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<800'],
        errors: ['rate<0.01'],
    },
};

const BASE    = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const SESSION = __ENV.CLINIC_SESSION || '';

// Core clinical endpoints — must stay fast under 50-VU load.
const ENDPOINTS = [
    '/owner/dashboard',
    '/owner/revenue-forecast',
    '/owner/activity-feed',
    '/owner/financial-report',
    '/owner/expense-intelligence',
    '/owner/inventory-health',
];

export default function () {
    const headers = SESSION
        ? { Cookie: `laravel_session=${SESSION}` }
        : {};

    for (const path of ENDPOINTS) {
        const res = http.get(`${BASE}${path}`, { headers, tags: { endpoint: path } });
        const ok  = check(res, {
            [`${path} → 200`]: (r) => r.status === 200,
        });
        errorRate.add(!ok);
    }

    sleep(0.5);
}
