<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductWriteRepositoryInterface;
use Illuminate\Support\Facades\DB;


class ProductWriteService
{
    public function __construct(
        protected ProductReadRepositoryInterface $productReadRepo,
        protected ProductWriteRepositoryInterface $productWriteRepo,
        protected ProductSnapshotService $snapshotService,
        protected ProductAttributeSyncService $attributeSyncService,
        protected CatalogCacheService $cacheService,
        protected CatalogOutboxService $outboxService,
    ) {}

    /**
     * Buat produk baru.
     *
     * @param array $data Data produk (sku, slug, name, price, dll)
     * @param array $categories Kategori: [category_id => ['is_primary' => bool], ...]
     * @return Product
     */
    public function createProduct(array $data, array $categories = []): Product
    {
        return DB::transaction(function () use ($data, $categories) {
            // 1. Set defaults
            $data['metadata_version'] = 1;
            $data['status'] = $data['status'] ?? 'active';

            // 2. Create produk
            $product = $this->productWriteRepo->create($data);

            // 3. Attach categories
            if (!empty($categories)) {
                $this->productWriteRepo->attachCategories($product, $categories);
            }

            // 4. Sync atribut berdasarkan primary category
            if (!empty($categories)) {
                try {
                    $this->attributeSyncService->syncProductAttributes($product->id);
                } catch (\Throwable $e) {
                    // Atribut sync gagal bukan blocker — log dan lanjut
                    report($e);
                }
            }

            // 5. Build initial snapshot
            $this->snapshotService->rebuildSnapshot($product->id);

            // 6. Record outbox
            $this->outboxService->recordProductCreated($product->id, [
                'sku' => $product->sku,
                'slug' => $product->slug,
                'name' => $product->name,
            ]);

            return $product->refresh();
        });
    }

    /**
     * Update produk.
     *
     * @param Product $product
     * @param array $data Data yang diupdate
     * @param array|null $categories Jika diberikan, sync categories. Null = tidak diubah.
     * @return Product
     */
    public function updateProduct(Product $product, array $data, ?array $categories = null): Product
    {
        return DB::transaction(function () use ($product, $data, $categories) {
            $oldSlug = $product->slug;

            // 1. Update produk
            $product = $this->productWriteRepo->update($product, $data);

            // 2. Sync categories jika diberikan
            $categoriesChanged = false;
            if ($categories !== null) {
                $this->productWriteRepo->syncCategories($product, $categories);
                $categoriesChanged = true;
            }

            // 3. Re-sync atribut jika kategori berubah
            if ($categoriesChanged) {
                try {
                    $this->attributeSyncService->syncProductAttributes($product->id);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // 4. Rebuild snapshot
            $this->snapshotService->rebuildSnapshot($product->id);

            // 5. Invalidate cache (old slug + new slug + ID)
            $this->cacheService->invalidateProduct($product->id, $oldSlug);
            if ($product->slug !== $oldSlug) {
                $this->cacheService->invalidateProduct($product->id, $product->slug);
            }

            // 6. Record outbox
            $this->outboxService->recordProductUpdated($product->id, array_keys($data));

            return $product->refresh();
        });
    }

    /**
     * Soft delete produk.
     */
    public function deleteProduct(Product $product): bool
    {
        return DB::transaction(function () use ($product) {
            $result = $this->productWriteRepo->delete($product);

            // Invalidate cache
            $this->cacheService->invalidateProduct($product->id, $product->slug);

            // Record outbox
            $this->outboxService->recordProductDeleted($product->id);

            return $result;
        });
    }
}
