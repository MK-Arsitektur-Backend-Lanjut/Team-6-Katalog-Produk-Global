<?php

namespace App\Jobs\Catalog;

use App\Services\Catalog\ProductSnapshotService;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Catalog\CatalogOutboxService;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job untuk rebuild metadata_snapshot secara massal.
 *
 * Digunakan saat:
 * - Definisi atribut kategori berubah → banyak produk perlu di-rebuild
 * - Maintenance: rebuild semua snapshot untuk consistency
 * - Perubahan struktur kategori yang mempengaruhi breadcrumbs banyak produk
 *
 * Processing strategy:
 * - Menerima array product IDs
 * - Proses per batch (configurable, default 500)
 * - Jika product IDs > batch_size, dispatch sub-jobs untuk setiap chunk
 * - Setiap rebuild: build snapshot → save → invalidate cache → record outbox
 */
class RebuildProductSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry 3 kali jika gagal.
     */
    public int $tries = 3;

    /**
     * Timeout 5 menit per job.
     */
    public int $timeout = 300;

    /**
     * @param array<int> $productIds Product IDs yang perlu di-rebuild
     * @param bool $shouldChunk Jika true dan productIds > batch_size, pecah ke sub-jobs
     */
    public function __construct(
        protected array $productIds,
        protected bool $shouldChunk = true,
    ) {}

    public function handle(
        ProductSnapshotService $snapshotService,
        CatalogCacheService $cacheService,
        CatalogOutboxService $outboxService,
        ProductReadRepositoryInterface $productReadRepo,
    ): void {
        $batchSize = (int) config('catalog.batch_size', 500);

        // Jika terlalu banyak, pecah ke sub-jobs
        if ($this->shouldChunk && count($this->productIds) > $batchSize) {
            $chunks = array_chunk($this->productIds, $batchSize);

            foreach ($chunks as $chunk) {
                self::dispatch($chunk, false)->onQueue('catalog');
            }

            Log::info('RebuildProductSnapshotJob: Dispatched ' . count($chunks) . ' sub-jobs for ' . count($this->productIds) . ' products.');
            return;
        }

        // Proses batch
        $successCount = 0;
        $failCount = 0;

        foreach ($this->productIds as $productId) {
            try {
                $snapshot = $snapshotService->rebuildSnapshot($productId);

                // Invalidate cache
                $product = $productReadRepo->findById($productId);
                if ($product) {
                    $cacheService->invalidateProduct($product->id, $product->slug);
                    $outboxService->recordSnapshotRebuilt($productId, $snapshot['sync']['version']);
                }

                $successCount++;
            } catch (\Throwable $e) {
                $failCount++;
                Log::warning("RebuildProductSnapshotJob: Failed to rebuild product #{$productId}: {$e->getMessage()}");
                report($e);
            }
        }

        Log::info("RebuildProductSnapshotJob: Completed. Success: {$successCount}, Failed: {$failCount}");
    }

    /**
     * Tag untuk Laravel Horizon monitoring.
     */
    public function tags(): array
    {
        return ['catalog', 'snapshot-rebuild', 'count:' . count($this->productIds)];
    }
}
