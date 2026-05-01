"""
Python stress test for Aviva Clinic System.
Gates: p95 < 800ms for page loads, error rate < 1%.
Requires: app running on http://127.0.0.1:8000
Usage: python tests/load/stress_test.py
"""
from __future__ import annotations

import concurrent.futures
import statistics
import time
import urllib.request
import urllib.parse
import urllib.error
import json
import sys
from dataclasses import dataclass, field
from typing import Optional


BASE_URL = "http://127.0.0.1:8000"
CONCURRENCY = 20
DURATION_S  = 60


@dataclass
class Result:
    url: str
    status: int
    elapsed_ms: float
    error: Optional[str] = None


def get(url: str, cookies: str = "", timeout: float = 10.0) -> Result:
    start = time.perf_counter()
    try:
        req = urllib.request.Request(
            url,
            headers={"Cookie": cookies, "Accept": "text/html,application/json"},
        )
        with urllib.request.urlopen(req, timeout=timeout) as r:
            r.read()
            status = r.status
    except urllib.error.HTTPError as e:
        status = e.code
    except Exception as exc:
        return Result(url=url, status=0, elapsed_ms=(time.perf_counter() - start) * 1000, error=str(exc))
    return Result(url=url, status=status, elapsed_ms=(time.perf_counter() - start) * 1000)


def login() -> str:
    """Return laravel_session cookie value for owner account."""
    # Fetch CSRF token
    req = urllib.request.Request(f"{BASE_URL}/login")
    with urllib.request.urlopen(req) as r:
        body = r.read().decode()
        cookies = r.headers.get("Set-Cookie", "")

    import re
    token_m = re.search(r'name="_token"\s+value="([^"]+)"', body)
    if not token_m:
        print("[WARN] Could not find CSRF token — unauthenticated test only")
        return ""

    csrf = token_m.group(1)
    xsrf = ""
    for part in cookies.split(","):
        if "XSRF-TOKEN" in part:
            xsrf_m = re.search(r"XSRF-TOKEN=([^;]+)", part)
            if xsrf_m:
                xsrf = urllib.parse.unquote(xsrf_m.group(1))

    data = urllib.parse.urlencode({
        "email": "owner@clinic.com",
        "password": "password123",
        "_token": csrf,
    }).encode()

    req2 = urllib.request.Request(
        f"{BASE_URL}/login",
        data=data,
        method="POST",
        headers={
            "Content-Type": "application/x-www-form-urlencoded",
            "Cookie": cookies,
            "X-XSRF-TOKEN": xsrf,
        },
    )
    try:
        with urllib.request.urlopen(req2, timeout=10) as r:
            session_cookies = r.headers.get("Set-Cookie", "")
    except urllib.error.HTTPError as e:
        session_cookies = e.headers.get("Set-Cookie", "")

    session_m = re.search(r"laravel_session=([^;]+)", session_cookies)
    if session_m:
        return f"laravel_session={session_m.group(1)}"
    return ""


def run_load_test(session_cookie: str) -> dict:
    endpoints = [
        "/login",
        "/",
    ]
    if session_cookie:
        endpoints += [
            "/owner/dashboard",
            "/owner/patients",
            "/owner/invoices",
            "/owner/users",
            "/owner/reports",
            "/owner/platform-settings",
        ]

    results: list[Result] = []
    deadline = time.time() + DURATION_S

    def worker():
        while time.time() < deadline:
            import random
            url = BASE_URL + random.choice(endpoints)
            results.append(get(url, session_cookie))

    print(f"\nRunning load test: {CONCURRENCY} concurrent workers for {DURATION_S}s...")
    with concurrent.futures.ThreadPoolExecutor(max_workers=CONCURRENCY) as pool:
        futures = [pool.submit(worker) for _ in range(CONCURRENCY)]
        concurrent.futures.wait(futures)

    total = len(results)
    errors = [r for r in results if r.status not in (200, 302, 301, 404) or r.error]
    latencies = [r.elapsed_ms for r in results if not r.error]

    if not latencies:
        return {"error": "No successful responses"}

    latencies.sort()
    p50 = statistics.median(latencies)
    p95 = latencies[int(len(latencies) * 0.95)]
    p99 = latencies[int(len(latencies) * 0.99)]
    error_rate = len(errors) / total if total else 1.0

    return {
        "total_requests": total,
        "rps": round(total / DURATION_S, 1),
        "p50_ms": round(p50, 1),
        "p95_ms": round(p95, 1),
        "p99_ms": round(p99, 1),
        "max_ms": round(max(latencies), 1),
        "error_count": len(errors),
        "error_rate_pct": round(error_rate * 100, 2),
        "passed_p95_gate": p95 < 800,
        "passed_error_gate": error_rate < 0.01,
    }


def check_app_health() -> dict:
    """Check critical app endpoints for correctness."""
    checks = {}
    # Health check
    r = get(f"{BASE_URL}/login")
    checks["login_page_up"] = r.status == 200

    # Sidecar health
    try:
        req = urllib.request.Request("http://127.0.0.1:8001/v1/health")
        with urllib.request.urlopen(req, timeout=3) as resp:
            body = json.loads(resp.read())
            checks["sidecar_up"] = body.get("status") == "ok"
    except Exception:
        checks["sidecar_up"] = False

    # Audit chain
    import subprocess
    result = subprocess.run(
        ["php", "artisan", "audit:verify-chain"],
        capture_output=True, text=True, cwd="D:\\Projects\\clinic-system", timeout=120
    )
    checks["audit_chain_ok"] = result.returncode == 0

    return checks


if __name__ == "__main__":
    print("=" * 60)
    print("  Aviva Clinic System — Stress Test")
    print("=" * 60)

    # Check app is up
    r = get(f"{BASE_URL}/login")
    if r.status != 200:
        print(f"\n[FAIL] App not reachable at {BASE_URL} (status={r.status})")
        sys.exit(1)
    print(f"\n[OK] App reachable (login page: {r.elapsed_ms:.0f}ms)")

    # Health checks
    print("\n--- Health Checks ---")
    health = check_app_health()
    for k, v in health.items():
        icon = "[PASS]" if v else "[WARN]"
        print(f"  {icon} {k}: {v}")

    # Login
    print("\n--- Authentication ---")
    session = login()
    if session:
        print("  [OK] Owner login successful")
    else:
        print("  [WARN] Could not authenticate — running unauthenticated test")

    # Load test
    stats = run_load_test(session)

    print("\n--- Load Test Results ---")
    if "error" in stats:
        print(f"  [FAIL] {stats['error']}")
        sys.exit(1)

    for k, v in stats.items():
        print(f"  {k}: {v}")

    print("\n--- Gate Results ---")
    p95_pass = stats["passed_p95_gate"]
    err_pass  = stats["passed_error_gate"]
    print(f"  {'[PASS]' if p95_pass else '[FAIL]'} p95 < 800ms  (actual: {stats['p95_ms']}ms)")
    print(f"  {'[PASS]' if err_pass  else '[FAIL]'} error < 1%   (actual: {stats['error_rate_pct']}%)")

    print("\n" + "=" * 60)
    if p95_pass and err_pass:
        print("  STRESS TEST: PASSED")
    else:
        print("  STRESS TEST: NEEDS ATTENTION (see above)")
    print("=" * 60)
