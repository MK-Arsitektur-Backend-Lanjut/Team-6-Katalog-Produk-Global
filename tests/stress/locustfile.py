"""
Stress test — Module 2: Search Optimization
Tool      : Locust (https://locust.io)
Target    : 1000 Virtual Users (Locust: "users")

Prerequisites:
  1. Install Locust:
       pip install locust

  2. Stack berjalan (docker compose up) dengan data ter-seed (10k produk)

  3. Run dengan Web UI (buka http://localhost:8089):
       locust -f tests/stress/locustfile.py --host=http://localhost:8000

  4. Run headless (tanpa UI) — setara dengan k6:
       locust -f tests/stress/locustfile.py \\
         --host=http://localhost:8000 \\
         --headless \\
         --users 1000 \\
         --spawn-rate 20 \\
         --run-time 2m \\
         --html tests/stress/locust-report.html

  5. Run via Docker (tanpa install Python):
       docker run --rm -it --network=host \\
         -v ${PWD}/tests/stress:/mnt/locust \\
         -p 8089:8089 \\
         locustio/locust \\
         -f /mnt/locust/locustfile.py \\
         --host=http://localhost:8000

Threshold yang ditargetkan (sama dengan k6):
  - Error rate  < 2%
  - Latency p95 < 3000 ms
  - Latency p99 < 6000 ms
"""

import random
import json
import os
import urllib.request
import urllib.parse
from locust import HttpUser, task, between, events

# ─── Konfigurasi via environment variable ────────────────────────────────────
TARGET_VUS = int(os.getenv("VUS", 1000))

# ─── Sample data untuk variasi request (sama dengan k6) ──────────────────────
SEARCH_PREFIXES = ["Sam", "App", "LG", "Son", "Nik", "Phi", "Asu", "Hua", "Xia", "One"]
KEYWORDS        = ["Samsung", "Apple", "LG", "phone", "Product", "laptop", "tablet", ""]
SORTS           = ["price_asc", "price_desc", "rating_desc", "rating_asc", "latest"]
LIMITS          = [10, 15, 25, 50]

# ─── Helpers ─────────────────────────────────────────────────────────────────
def pick(lst: list):
    return random.choice(lst)

def rand_int(min_val: int, max_val: int) -> int:
    return random.randint(min_val, max_val)

def rand_product_ids(n: int = 20) -> list:
    return random.sample(range(1, 5001), min(n, 5000))


# ─── Warmup SEKALI sebelum test dimulai (setara setup() di k6) ───────────────
@events.test_start.add_listener
def warmup_cache(environment, **kwargs):
    """
    Dipanggil SATU KALI sebelum user manapun mulai.
    Setara dengan fungsi setup() di k6.

    Tujuan: memanaskan Redis cache agar test utama tidak dibebani
    cold-start DB query yang mahal.
    """
    base_url = environment.host or "http://localhost:8000"
    headers  = {"Accept": "application/json", "Content-Type": "application/json"}

    print(f"\n[warmup] Memanaskan cache Redis untuk {base_url} ...")

    def _get(path, params=None):
        url = f"{base_url}{path}"
        if params:
            url += "?" + urllib.parse.urlencode(params)
        req = urllib.request.Request(url, headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                status = r.status
        except Exception as e:
            status = f"ERROR({e})"
        print(f"[warmup]  GET {url} → {status}")

    def _post(path, body: dict):
        url  = f"{base_url}{path}"
        data = json.dumps(body).encode("utf-8")
        req  = urllib.request.Request(url, data=data, headers=headers, method="POST")
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                status = r.status
        except Exception as e:
            status = f"ERROR({e})"
        print(f"[warmup]  POST {url} → {status}")

    _get("/api/v1/catalog/products/search",       {"keyword": "Samsung", "limit": "15"})
    _get("/api/v1/catalog/products/autocomplete", {"query": "Sam"})
    _get("/api/v1/catalog/products/facets",       {"keyword": "phone"})
    _get("/api/v1/catalog/products/suggest",      {"product_id": "1"})
    _get("/api/v1/catalog/products/stats")
    _post("/api/v1/catalog/products/bulk-search", {"ids": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]})

    print("[warmup] Selesai — Redis cache siap.\n")


# ─── User class utama ─────────────────────────────────────────────────────────
class SearchOptimizationUser(HttpUser):
    """
    Simulasi user yang berinteraksi dengan endpoint Search Optimization.

    Distribusi traffic (sama dengan k6):
      - 40%  → search         (endpoint utama)
      - 25%  → autocomplete   (typed search)
      - 15%  → facets         (filter panel)
      - 10%  → suggest        (recommendation widget)
      -  7%  → bulk-search    (batch lookup)
      -  3%  → stats          (analytics/dashboard)
    """

    # Think time antar request: 0.1 – 0.5 detik (sama dengan k6 sleep(1-5)/10)
    wait_time = between(0.1, 0.5)

    # Header default untuk semua request
    headers = {"Accept": "application/json"}

    # ── Endpoint: Search (40%) ────────────────────────────────────────────────
    @task(40)
    def search(self):
        min_price = rand_int(1, 5) * 100_000
        max_price = min_price + rand_int(1, 10) * 100_000

        params = {
            "keyword":   pick(KEYWORDS),
            "sort":      pick(SORTS),
            "page":      str(rand_int(1, 10)),
            "limit":     str(pick(LIMITS)),
            "min_price": str(min_price),
            "max_price": str(max_price),
        }
        # Hapus keyword kosong agar tidak dikirim sebagai param kosong
        params = {k: v for k, v in params.items() if v != ""}

        with self.client.get(
            "/api/v1/catalog/products/search",
            params=params,
            headers=self.headers,
            name="/api/v1/catalog/products/search",
            catch_response=True,
        ) as resp:
            self._validate(resp, "search")

    # ── Endpoint: Autocomplete (25%) ─────────────────────────────────────────
    @task(25)
    def autocomplete(self):
        with self.client.get(
            "/api/v1/catalog/products/autocomplete",
            params={"query": pick(SEARCH_PREFIXES)},
            headers=self.headers,
            name="/api/v1/catalog/products/autocomplete",
            catch_response=True,
        ) as resp:
            self._validate(resp, "autocomplete")

    # ── Endpoint: Facets (15%) ───────────────────────────────────────────────
    @task(15)
    def facets(self):
        kw = pick(KEYWORDS) if random.random() > 0.3 else ""
        params = {"keyword": kw} if kw else {}

        with self.client.get(
            "/api/v1/catalog/products/facets",
            params=params,
            headers=self.headers,
            name="/api/v1/catalog/products/facets",
            catch_response=True,
        ) as resp:
            self._validate(resp, "facets")

    # ── Endpoint: Suggest (10%) ──────────────────────────────────────────────
    @task(10)
    def suggest(self):
        with self.client.get(
            "/api/v1/catalog/products/suggest",
            params={"product_id": rand_int(1, 3000)},
            headers=self.headers,
            name="/api/v1/catalog/products/suggest",
            catch_response=True,
        ) as resp:
            self._validate(resp, "suggest")

    # ── Endpoint: Bulk Search (7%) ───────────────────────────────────────────
    @task(7)
    def bulk_search(self):
        count = rand_int(5, 30)
        payload = json.dumps({"ids": rand_product_ids(count)})

        with self.client.post(
            "/api/v1/catalog/products/bulk-search",
            data=payload,
            headers={**self.headers, "Content-Type": "application/json"},
            name="/api/v1/catalog/products/bulk-search",
            catch_response=True,
        ) as resp:
            self._validate(resp, "bulk_search")

    # ── Endpoint: Stats (3%) ─────────────────────────────────────────────────
    @task(3)
    def stats(self):
        with self.client.get(
            "/api/v1/catalog/products/stats",
            headers=self.headers,
            name="/api/v1/catalog/products/stats",
            catch_response=True,
        ) as resp:
            self._validate(resp, "stats")

    # ── Validator response ────────────────────────────────────────────────────
    def _validate(self, resp, endpoint_name: str):
        """
        Validasi response: status 200 dan body mengandung key 'data'.
        Jika gagal, tandai sebagai failure (dicatat Locust sebagai error).
        """
        if resp.status_code != 200:
            resp.failure(
                f"[{endpoint_name}] Expected 200, got {resp.status_code}"
            )
            return

        try:
            body = resp.json()
            if not isinstance(body, dict) or "data" not in body:
                resp.failure(
                    f"[{endpoint_name}] Response body missing 'data' key"
                )
            else:
                resp.success()
        except Exception as e:
            resp.failure(f"[{endpoint_name}] Invalid JSON: {e}")


# ─── Event hooks: custom threshold check di akhir test ───────────────────────
@events.quitting.add_listener
def check_thresholds(environment, **kwargs):
    """
    Setelah test selesai, cek threshold secara manual dan print summary.
    Jika threshold tidak terpenuhi, set exit code 1 (gagal).

    Threshold (sama dengan k6):
      - error_rate < 2%
      - p95 latency < 3000 ms
      - p99 latency < 6000 ms
    """
    stats = environment.runner.stats.total

    total_req   = stats.num_requests
    total_fail  = stats.num_failures
    error_rate  = (total_fail / total_req * 100) if total_req > 0 else 0

    p50 = stats.get_response_time_percentile(0.50) or 0
    p95 = stats.get_response_time_percentile(0.95) or 0
    p99 = stats.get_response_time_percentile(0.99) or 0
    avg = stats.avg_response_time or 0
    rps = stats.current_rps

    pass_err = error_rate < 2.0
    pass_p95 = p95 < 3000
    pass_p99 = p99 < 6000

    sep = "═" * 54
    print(f"\n╔{sep}╗")
    print(f"║{'  Search Optimization — Locust Stress Test Result  ':^54}║")
    print(f"╠{sep}╣")
    print(f"║  {'Total Requests':<18}: {str(total_req):<34}║")
    print(f"║  {'Failed Requests':<18}: {str(total_fail):<34}║")
    print(f"║  {'Error Rate':<18}: {f'{error_rate:.2f}%':<34}║")
    print(f"║  {'Req/s':<18}: {f'{rps:.2f}':<34}║")
    print(f"╠{sep}╣")
    print(f"║  {'Latency avg':<18}: {f'{avg:.2f} ms':<34}║")
    print(f"║  {'Latency p50':<18}: {f'{p50:.2f} ms':<34}║")
    print(f"║  {'Latency p95':<18}: {f'{p95:.2f} ms':<34}║")
    print(f"║  {'Latency p99':<18}: {f'{p99:.2f} ms':<34}║")
    print(f"╠{sep}╣")
    print(f"║  {'Threshold err < 2%':<30}: {'✅ PASS' if pass_err else '❌ FAIL':<23}║")
    print(f"║  {'Threshold p95 < 3000ms':<30}: {'✅ PASS' if pass_p95 else '❌ FAIL':<23}║")
    print(f"║  {'Threshold p99 < 6000ms':<30}: {'✅ PASS' if pass_p99 else '❌ FAIL':<23}║")
    print(f"╚{sep}╝\n")

    # Set exit code 1 jika ada threshold yang gagal
    if not all([pass_err, pass_p95, pass_p99]):
        environment.process_exit_code = 1
