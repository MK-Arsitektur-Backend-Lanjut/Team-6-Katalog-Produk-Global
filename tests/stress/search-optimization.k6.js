/**
 * Stress test — Module 2: Search Optimization
 * Target: 1000 Virtual Users
 *
 * Prerequisites:
 *   1. Stack berjalan (docker compose up) dengan data ter-seed (10k produk)
 *   2. Jalankan via Docker:
 *      docker run --rm -i --network=host -v ${PWD}/tests/stress:/scripts grafana/k6 run /scripts/search-optimization.k6.js
 *
 * Atau dengan custom VU & durasi:
 *   docker run --rm -i --network=host -v ${PWD}/tests/stress:/scripts \
 *     -e VUS=1000 -e DURATION=2m grafana/k6 run /scripts/search-optimization.k6.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const BASE_URL  = __ENV.BASE_URL  || 'http://localhost:8000';
const VUS       = Number(__ENV.VUS      || 1000);
const DURATION  = __ENV.DURATION  || '2m';
const RAMP_UP   = __ENV.RAMP_UP   || '30s';
const RAMP_DOWN = __ENV.RAMP_DOWN || '15s';

// ─── Sample data untuk variasi request ─────────────────────────────────────
const SEARCH_PREFIXES = ['Sam', 'App', 'LG', 'Son', 'Nik', 'Phi', 'Asu', 'Hua', 'Xia', 'One'];
const KEYWORDS        = ['Samsung', 'Apple', 'LG', 'phone', 'Product', 'laptop', 'tablet', ''];
const SORTS           = ['price_asc', 'price_desc', 'rating_desc', 'rating_asc', 'latest'];
const LIMITS          = [10, 15, 25, 50];

// ─── Custom metrics per endpoint ────────────────────────────────────────────
const endpointDuration = new Trend('search_opt_endpoint_duration', true);
const endpointErrors   = new Rate('search_opt_endpoint_errors');

const requestsByEndpoint = {
  search:       new Counter('req_search'),
  autocomplete: new Counter('req_autocomplete'),
  facets:       new Counter('req_facets'),
  bulkSearch:   new Counter('req_bulk_search'),
  suggest:      new Counter('req_suggest'),
  stats:        new Counter('req_stats'),
};

// ─── Skenario & threshold ────────────────────────────────────────────────────
export const options = {
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
  scenarios: {
    search_optimization: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        // Ramp-up bertahap agar server tidak langsung dibanjiri
        { duration: '10s', target: 100  },   // 0 → 100 VU dalam 10 detik
        { duration: '10s', target: 300  },   // 100 → 300 VU
        { duration: '10s', target: 600  },   // 300 → 600 VU
        { duration: RAMP_UP, target: VUS },  // 600 → 1000 VU (30 detik penuh)
        { duration: DURATION, target: VUS }, // Tahan di 1000 VU selama DURATION
        { duration: RAMP_DOWN, target: 0  }, // Ramp-down
      ],
      gracefulRampDown: '15s',
    },
  },

  thresholds: {
    // Error rate < 2%
    http_req_failed:             ['rate<0.02'],
    search_opt_endpoint_errors:  ['rate<0.02'],

    // Latency: p95 < 3 detik, p99 < 6 detik (threshold lebih longgar untuk 1000 VU)
    http_req_duration:           ['p(95)<3000', 'p(99)<6000'],
    search_opt_endpoint_duration:['p(95)<3000'],

    // Pastikan setiap endpoint mendapat traffic
    req_search:      ['count>100'],
    req_autocomplete:['count>100'],
    req_facets:      ['count>50'],
    req_suggest:     ['count>50'],
    req_bulk_search: ['count>50'],
    req_stats:       ['count>10'],
  },
};

// ─── Helpers ─────────────────────────────────────────────────────────────────
function pick(arr)            { return arr[Math.floor(Math.random() * arr.length)]; }
function randInt(min, max)    { return Math.floor(Math.random() * (max - min + 1)) + min; }
function randProductIds(n=20) {
  const ids = new Set();
  while (ids.size < n) ids.add(randInt(1, 5000));
  return Array.from(ids);
}

function record(name, res) {
  endpointDuration.add(res.timings.duration, { endpoint: name });
  requestsByEndpoint[name].add(1);

  const ok = check(res, {
    [`${name}: status 200`]: (r) => r.status === 200,
    [`${name}: body has data`]: (r) => {
      try {
        const b = r.json();
        return b !== null && typeof b === 'object' && 'data' in b;
      } catch { return false; }
    },
  });

  endpointErrors.add(!ok ? 1 : 0);
}

// ─── Warmup (dijalankan sekali sebelum test) ─────────────────────────────────
export function setup() {
  const h = { Accept: 'application/json', 'Content-Type': 'application/json' };
  console.log(`[warmup] Memanaskan cache untuk ${BASE_URL}...`);

  // Warmup semua endpoint agar cache Redis terisi sebelum beban penuh
  http.get(`${BASE_URL}/api/v1/catalog/products/search?keyword=Samsung&limit=15`, { headers: h });
  http.get(`${BASE_URL}/api/v1/catalog/products/autocomplete?query=Sam`,          { headers: h });
  http.get(`${BASE_URL}/api/v1/catalog/products/facets?keyword=phone`,            { headers: h });
  http.get(`${BASE_URL}/api/v1/catalog/products/suggest?product_id=1`,            { headers: h });
  http.get(`${BASE_URL}/api/v1/catalog/products/stats`,                            { headers: h });
  http.post(
    `${BASE_URL}/api/v1/catalog/products/bulk-search`,
    JSON.stringify({ ids: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10] }),
    { headers: h }
  );

  console.log('[warmup] Selesai.');
}

// ─── Fungsi utama (dijalankan setiap VU) ─────────────────────────────────────
export default function () {
  const headers = { Accept: 'application/json' };
  const roll    = Math.random();

  /**
   * Distribusi traffic yang realistis:
   * - 40% → search (paling banyak, endpoint utama)
   * - 25% → autocomplete (typed search)
   * - 15% → facets (filter panel)
   * - 10% → suggest (recommendation widget)
   * - 7%  → bulk-search (batch lookup)
   * - 3%  → stats (analytics/dashboard)
   */
  if (roll < 0.40) {
    // ── Search dengan berbagai kombinasi filter ──
    const minPrice = randInt(1, 5) * 100000;
    const maxPrice = minPrice + randInt(1, 10) * 100000; // selalu min <= max

    const params = {
      keyword:   pick(KEYWORDS),
      sort:      pick(SORTS),
      page:      String(randInt(1, 10)),
      limit:     String(pick(LIMITS)),
      min_price: String(minPrice),
      max_price: String(maxPrice),
    };

    const query = Object.entries(params)
      .filter(([, v]) => v !== '')
      .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
      .join('&');

    record('search', http.get(`${BASE_URL}/api/v1/catalog/products/search?${query}`, { headers }));

  } else if (roll < 0.65) {
    // ── Autocomplete ──
    record('autocomplete', http.get(
      `${BASE_URL}/api/v1/catalog/products/autocomplete?query=${encodeURIComponent(pick(SEARCH_PREFIXES))}`,
      { headers }
    ));

  } else if (roll < 0.80) {
    // ── Facets ──
    const kw = Math.random() > 0.3 ? pick(KEYWORDS) : '';
    record('facets', http.get(
      `${BASE_URL}/api/v1/catalog/products/facets${kw ? '?keyword=' + encodeURIComponent(kw) : ''}`,
      { headers }
    ));

  } else if (roll < 0.90) {
    // ── Suggest ──
    record('suggest', http.get(
      `${BASE_URL}/api/v1/catalog/products/suggest?product_id=${randInt(1, 3000)}`,
      { headers }
    ));

  } else if (roll < 0.97) {
    // ── Bulk Search ──
    const count = randInt(5, 30);
    record('bulkSearch', http.post(
      `${BASE_URL}/api/v1/catalog/products/bulk-search`,
      JSON.stringify({ ids: randProductIds(count) }),
      { headers: { ...headers, 'Content-Type': 'application/json' } }
    ));

  } else {
    // ── Stats ──
    record('stats', http.get(`${BASE_URL}/api/v1/catalog/products/stats`, { headers }));
  }

  // Jeda kecil antar request (simulasi think time user)
  sleep(randInt(1, 5) / 10);
}

// ─── Ringkasan hasil ──────────────────────────────────────────────────────────
export function handleSummary(data) {
  const m      = data.metrics;
  const p50    = m.http_req_duration?.values?.['p(50)']  ?? m.http_req_duration?.values?.['med'] ?? 0;
  const p95    = m.http_req_duration?.values?.['p(95)']  ?? 0;
  const p99    = m.http_req_duration?.values?.['p(99)']  ?? 0;
  const avg    = m.http_req_duration?.values?.['avg']    ?? 0;
  const failed = m.http_req_failed?.values?.rate         ?? 0;
  const total  = m.http_reqs?.values?.count              ?? 0;
  const rps    = m.http_reqs?.values?.['rate']           ?? 0;

  const epDur  = m.search_opt_endpoint_duration?.values;

  const lines = [
    '',
    '╔══════════════════════════════════════════════════════╗',
    '║       Search Optimization — Stress Test Result       ║',
    '╠══════════════════════════════════════════════════════╣',
    `║  Base URL   : ${BASE_URL.padEnd(37)}║`,
    `║  VUs        : ${String(VUS).padEnd(37)}║`,
    `║  Duration   : ${DURATION.padEnd(37)}║`,
    '╠══════════════════════════════════════════════════════╣',
    `║  Total Req  : ${String(total).padEnd(37)}║`,
    `║  Req/s      : ${rps.toFixed(2).padEnd(37)}║`,
    `║  Error Rate : ${(failed * 100).toFixed(2).padEnd(36)}%║`,
    '╠══════════════════════════════════════════════════════╣',
    `║  Latency avg: ${avg.toFixed(2).padEnd(33)} ms   ║`,
    `║  Latency p50: ${p50.toFixed(2).padEnd(33)} ms   ║`,
    `║  Latency p95: ${p95.toFixed(2).padEnd(33)} ms   ║`,
    `║  Latency p99: ${p99.toFixed(2).padEnd(33)} ms   ║`,
    '╠══════════════════════════════════════════════════════╣',
    `║  Threshold p95 < 3000ms : ${(p95 < 3000 ? '✅ PASS' : '❌ FAIL').padEnd(26)}║`,
    `║  Threshold err < 2%     : ${(failed < 0.02 ? '✅ PASS' : '❌ FAIL').padEnd(26)}║`,
    '╚══════════════════════════════════════════════════════╝',
    '',
  ].join('\n');

  return { stdout: lines };
}
