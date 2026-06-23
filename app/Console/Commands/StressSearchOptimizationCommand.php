<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class StressSearchOptimizationCommand extends Command
{
    protected $signature = 'catalog:stress-search
                            {--base-url=http://localhost:8000 : Target API base URL}
                            {--requests=200 : Total requests to send}
                            {--concurrency=20 : Concurrent requests per batch}
                            {--warmup=10 : Warmup requests before measurement}
                            {--timeout=30 : HTTP timeout in seconds}';

    protected $description = 'Stress test Search Optimization endpoints (search, autocomplete, facets, bulk-search, suggest, stats)';

  /** @var list<string> */
    protected array $searchPrefixes = ['Sam', 'App', 'LG', 'Son', 'Nik', 'Ike', 'Phi', 'Asu'];

  /** @var list<string> */
    protected array $keywords = ['Samsung', 'Apple', 'LG', 'phone', 'Product', ''];

  /** @var list<string> */
    protected array $sorts = ['price_asc', 'price_desc', 'rating_desc', 'latest'];

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->option('base-url'), '/');
        $totalRequests = max(1, (int) $this->option('requests'));
        $concurrency = max(1, (int) $this->option('concurrency'));
        $warmup = max(0, (int) $this->option('warmup'));
        $timeout = max(1, (int) $this->option('timeout'));

        $this->info("Stress testing Search Optimization at {$baseUrl}");
        $this->line("Requests: {$totalRequests} | Concurrency: {$concurrency} | Warmup: {$warmup}");

        if ($warmup > 0) {
            $this->comment("Warming up cache ({$warmup} requests)...");
            $this->runBatches($baseUrl, $warmup, $concurrency, $timeout, collectStats: false);
        }

        $this->comment('Running stress test...');
        $stats = $this->runBatches($baseUrl, $totalRequests, $concurrency, $timeout, collectStats: true);

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total requests', $stats['total']],
                ['Successful (2xx)', $stats['success']],
                ['Failed', $stats['failed']],
                ['Error rate', number_format($stats['error_rate'] * 100, 2) . '%'],
                ['Min (ms)', number_format($stats['min'], 2)],
                ['Avg (ms)', number_format($stats['avg'], 2)],
                ['p50 (ms)', number_format($stats['p50'], 2)],
                ['p95 (ms)', number_format($stats['p95'], 2)],
                ['p99 (ms)', number_format($stats['p99'], 2)],
                ['Max (ms)', number_format($stats['max'], 2)],
                ['RPS', number_format($stats['rps'], 2)],
            ]
        );

        if (! empty($stats['by_endpoint'])) {
            $this->newLine();
            $this->info('Per-endpoint p95 (ms):');
            $rows = [];
            foreach ($stats['by_endpoint'] as $endpoint => $durations) {
                sort($durations);
                $rows[] = [$endpoint, number_format($this->percentile($durations, 95), 2), count($durations)];
            }
            $this->table(['Endpoint', 'p95 (ms)', 'Requests'], $rows);
        }

        if ($stats['error_rate'] > 0.02) {
            $this->error('Stress test failed: error rate exceeds 2% threshold.');

            return self::FAILURE;
        }

        if ($stats['p95'] > 2000) {
            $this->warn('Stress test warning: p95 latency exceeds 2000ms threshold.');

            return self::FAILURE;
        }

        $this->info('Stress test passed.');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *   total: int,
     *   success: int,
     *   failed: int,
     *   error_rate: float,
     *   min: float,
     *   avg: float,
     *   p50: float,
     *   p95: float,
     *   p99: float,
     *   max: float,
     *   rps: float,
     *   by_endpoint: array<string, list<float>>
     * }
     */
    protected function runBatches(
        string $baseUrl,
        int $totalRequests,
        int $concurrency,
        int $timeout,
        bool $collectStats,
    ): array {
        $durations = [];
        $byEndpoint = [];
        $success = 0;
        $failed = 0;
        $sent = 0;
        $startedAt = microtime(true);

        while ($sent < $totalRequests) {
            $batchSize = min($concurrency, $totalRequests - $sent);
            $scenarios = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $scenarios[] = $this->buildScenario($baseUrl);
            }

            $batchStartedAt = microtime(true);

            $responses = Http::timeout($timeout)
                ->acceptJson()
                ->pool(function (Pool $pool) use ($scenarios) {
                    $requests = [];
                    foreach ($scenarios as $index => $scenario) {
                        $request = $pool->as((string) $index);

                        if ($scenario['method'] === 'POST') {
                            $requests[] = $request
                                ->withHeaders($scenario['headers'] ?? [])
                                ->post($scenario['url'], $scenario['body'] ?? []);
                        } else {
                            $requests[] = $request
                                ->withHeaders($scenario['headers'] ?? [])
                                ->get($scenario['url']);
                        }
                    }

                    return $requests;
                });

            $batchDurationMs = (microtime(true) - $batchStartedAt) * 1000;
            $perRequestMs = $batchSize > 0 ? $batchDurationMs / $batchSize : 0;

            foreach ($scenarios as $index => $scenario) {
                $response = $responses[(string) $index] ?? null;
                $ok = $response instanceof Response && $response->successful();

                if ($ok) {
                    $success++;
                } else {
                    $failed++;
                    if ($response instanceof Throwable && $failed === 1 && $collectStats) {
                        $this->warn('Request failed: ' . $response->getMessage());
                    }
                }

                if ($collectStats) {
                    $endpoint = $scenario['endpoint'];
                    $durations[] = $perRequestMs;
                    $byEndpoint[$endpoint][] = $perRequestMs;
                }
            }

            $sent += $batchSize;
        }

        $elapsed = max(microtime(true) - $startedAt, 0.001);
        $total = $success + $failed;

        if (! $collectStats) {
            return [
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
                'error_rate' => $total > 0 ? $failed / $total : 0,
                'min' => 0,
                'avg' => 0,
                'p50' => 0,
                'p95' => 0,
                'p99' => 0,
                'max' => 0,
                'rps' => $total / $elapsed,
                'by_endpoint' => [],
            ];
        }

        sort($durations);

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'error_rate' => $total > 0 ? $failed / $total : 0,
            'min' => $durations[0] ?? 0,
            'avg' => ! empty($durations) ? array_sum($durations) / count($durations) : 0,
            'p50' => $this->percentile($durations, 50),
            'p95' => $this->percentile($durations, 95),
            'p99' => $this->percentile($durations, 99),
            'max' => $durations[array_key_last($durations)] ?? 0,
            'rps' => $total / $elapsed,
            'by_endpoint' => $byEndpoint,
        ];
    }

    /**
     * @return array{endpoint: string, method: string, url: string, body?: array<string, mixed>, headers?: array<string, string>}
     */
    protected function buildScenario(string $baseUrl): array
    {
        $roll = random_int(1, 100);

        if ($roll <= 40) {
            $params = array_filter([
                'keyword' => $this->pick($this->keywords),
                'sort' => $this->pick($this->sorts),
                'page' => (string) random_int(1, 20),
                'limit' => (string) $this->pick(['10', '15', '25', '50']),
                'min_price' => (string) (random_int(1, 5) * 100000),
                'max_price' => (string) (random_int(5, 15) * 100000),
            ], fn ($value) => $value !== '');

            return [
                'endpoint' => 'search',
                'method' => 'GET',
                'url' => $baseUrl . '/api/v1/catalog/products/search?' . http_build_query($params),
            ];
        }

        if ($roll <= 65) {
            return [
                'endpoint' => 'autocomplete',
                'method' => 'GET',
                'url' => $baseUrl . '/api/v1/catalog/products/autocomplete?' . http_build_query([
                    'query' => $this->pick($this->searchPrefixes),
                ]),
            ];
        }

        if ($roll <= 80) {
            return [
                'endpoint' => 'facets',
                'method' => 'GET',
                'url' => $baseUrl . '/api/v1/catalog/products/facets?' . http_build_query([
                    'keyword' => $this->pick($this->keywords),
                ]),
            ];
        }

        if ($roll <= 90) {
            return [
                'endpoint' => 'suggest',
                'method' => 'GET',
                'url' => $baseUrl . '/api/v1/catalog/products/suggest?' . http_build_query([
                    'product_id' => random_int(1, 2000),
                ]),
            ];
        }

        if ($roll <= 95) {
            $ids = collect(range(1, 5000))
                ->shuffle()
                ->take(random_int(5, 50))
                ->values()
                ->all();

            return [
                'endpoint' => 'bulk-search',
                'method' => 'POST',
                'url' => $baseUrl . '/api/v1/catalog/products/bulk-search',
                'body' => ['ids' => $ids],
                'headers' => ['Content-Type' => 'application/json'],
            ];
        }

        return [
            'endpoint' => 'stats',
            'method' => 'GET',
            'url' => $baseUrl . '/api/v1/catalog/products/stats',
        ];
    }

    /**
     * @param list<float> $values
     */
    protected function percentile(array $values, int $percentile): float
    {
        if ($values === []) {
            return 0;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return $values[$index];
    }

    protected function pick(array $items): string
    {
        return (string) $items[array_rand($items)];
    }
}
