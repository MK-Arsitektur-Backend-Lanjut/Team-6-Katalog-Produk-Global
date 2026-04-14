<?php

namespace App\Jobs\Catalog;

use App\Services\Catalog\ProductAttributeSyncService;
use App\Services\Catalog\CatalogCacheService;
use App\Repositories\Contracts\Catalog\CategoryRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job untuk sync atribut semua produk dalam satu kategori.
 *
 * Digunakan saat:
 * - Definisi category_attributes berubah (atribut ditambah/dihapus dari kategori)
 * - Kategori dipindah (parent berubah) → inherited attributes berubah
 * - Admin menambah atribut baru ke kategori ancestor
 *
 * Flow:
 * 1. Ambil semua product IDs yang terkait kategori + descendant categories
 * 2. Untuk setiap produk, re-sync atribut berdasarkan primary category
 * 3. Rebuild snapshot + invalidate cache
 */
class SyncCategoryProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 menit — bisa banyak produk

    /**
     * @param int $categoryId Kategori yang atributnya berubah
     * @param bool $includeDescendants Jika true, sync juga produk di descendant categories
     */
    public function __construct(
        protected int $categoryId,
        protected bool $includeDescendants = true,
    ) {}

    public function handle(
        ProductAttributeSyncService $syncService,
        CategoryRepositoryInterface $categoryRepo,
        ProductReadRepositoryInterface $productReadRepo,
        CatalogCacheService $cacheService,
    ): void {
        $batchSize = (int) config('catalog.batch_size', 500);

        // Kumpulkan semua category IDs yang terdampak
        $categoryIds = [$this->categoryId];

        if ($this->includeDescendants) {
            $descendants = $categoryRepo->getDescendants($this->categoryId);
            $categoryIds = array_merge($categoryIds, $descendants->pluck('id')->toArray());
        }

        // Kumpulkan semua product IDs dari semua kategori terdampak
        $allProductIds = collect();
        foreach ($categoryIds as $catId) {
            $productIds = $productReadRepo->getProductIdsByCategoryId($catId);
            $allProductIds = $allProductIds->merge($productIds);
        }

        $uniqueProductIds = $allProductIds->unique()->values()->toArray();

        Log::info("SyncCategoryProductsJob: Syncing " . count($uniqueProductIds) . " products for category #{$this->categoryId} (+descendants: " . count($categoryIds) . " categories)");

        // Jika terlalu banyak, dispatch RebuildProductSnapshotJob per chunk
        if (count($uniqueProductIds) > $batchSize) {
            $chunks = array_chunk($uniqueProductIds, $batchSize);

            foreach ($chunks as $chunk) {
                // Sync atribut per batch
                foreach ($chunk as $productId) {
                    try {
                        $syncService->syncProductAttributes($productId);
                    } catch (\Throwable $e) {
                        Log::warning("SyncCategoryProductsJob: Failed to sync product #{$productId}: {$e->getMessage()}");
                        report($e);
                    }
                }
            }
        } else {
            // Sync langsung
            $successCount = 0;
            $failCount = 0;

            foreach ($uniqueProductIds as $productId) {
                try {
                    $syncService->syncProductAttributes($productId);
                    $successCount++;
                } catch (\Throwable $e) {
                    $failCount++;
                    Log::warning("SyncCategoryProductsJob: Failed to sync product #{$productId}: {$e->getMessage()}");
                    report($e);
                }
            }

            Log::info("SyncCategoryProductsJob: Completed. Success: {$successCount}, Failed: {$failCount}");
        }

        // Invalidate category cache
        $cacheService->invalidateCategoryTree();
    }

    public function tags(): array
    {
        return ['catalog', 'category-sync', 'category:' . $this->categoryId];
    }
}
