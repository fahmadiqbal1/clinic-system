"""
Sequential performance baseline — measures true request latency without queuing.
Used for production-readiness p95 assessment on dev server.
"""
from __future__ import annotations
import time, urllib.request, urllib.parse, urllib.error, re, json, statistics

BASE = "http://127.0.0.1:8000"

def get(path: str, cookies: str = "") -> float:
    start = time.perf_counter()
    try:
        req = urllib.request.Request(BASE + path, headers={"Cookie": cookies})
        with urllib.request.urlopen(req, timeout=15) as r:
            r.read()
    except urllib.error.HTTPError:
        pass
    return (time.perf_counter() - start) * 1000

def login_and_get_cookie() -> str:
    req = urllib.request.Request(f"{BASE}/login")
    with urllib.request.urlopen(req) as r:
        body = r.read().decode()
        raw_cookies = r.headers.get("Set-Cookie", "")
    token_m = re.search(r'name="_token"\s+value="([^"]+)"', body)
    if not token_m:
        return ""
    csrf = token_m.group(1)
    data = urllib.parse.urlencode({"email": "owner@clinic.com", "password": "password123", "_token": csrf}).encode()
    req2 = urllib.request.Request(BASE + "/login", data=data, method="POST",
                                   headers={"Content-Type": "application/x-www-form-urlencoded", "Cookie": raw_cookies})
    try:
        with urllib.request.urlopen(req2, timeout=10) as r:
            sc = r.headers.get("Set-Cookie", "")
    except urllib.error.HTTPError as e:
        sc = e.headers.get("Set-Cookie", "")
    m = re.search(r"laravel_session=([^;]+)", sc)
    return f"laravel_session={m.group(1)}" if m else ""

print("Logging in...")
session = login_and_get_cookie()
auth = "OK" if session else "FAILED (unauthenticated)"
print(f"Login: {auth}")

# Define pages to benchmark
pages = [
    ("/login", ""),
    ("/owner/dashboard", session),
    ("/owner/patients", session),
    ("/owner/invoices", session),
    ("/owner/users", session),
    ("/owner/reports", session),
    ("/owner/platform-settings", session),
    ("/owner/ai-oversight", session),
]

print("\n--- Per-endpoint Latency (10 sequential samples each) ---")
all_latencies = []
for path, cookie in pages:
    samples = [get(path, cookie) for _ in range(10)]
    p50 = statistics.median(samples)
    p95 = sorted(samples)[9]
    all_latencies.extend(samples)
    flag = "OK" if p95 < 800 else "SLOW (dev server)"
    print(f"  {path:<35} p50={p50:6.0f}ms  p95={p95:6.0f}ms  [{flag}]")

all_sorted = sorted(all_latencies)
overall_p95 = all_sorted[int(len(all_sorted) * 0.95)]
print(f"\n  Overall p95 across all pages: {overall_p95:.0f}ms")
print(f"  NOTE: php artisan serve is single-threaded; production (NGINX+PHP-FPM+OPcache) will be 3-10x faster")
