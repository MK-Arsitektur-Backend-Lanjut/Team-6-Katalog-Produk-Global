<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductWriteRepositoryInterface;
use App\Repositories\Contracts\Catalog\CategoryRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductImageRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductAttributeValueRepositoryInterface;

/**
 * Service untuk membangun dan merebuild metadata_snapshot JSON.
 *
 * Snapshot adalah denormalized view dari seluruh data produk yang siap baca:
 * - Data produk utama
 * - Kategori utama + breadcrumbs
 * - Semua kategori
 * - Atribut beserta value
 * - Gambar produk
 * - Metadata sync (version, timestamp)
 *
 * Dengan snapshot, API detail produk hanya perlu 1 read dari kolom JSON,
 * tanpa JOIN ke tabel lain.
 */
class ProductSnapshotService
{
    public function __construct(
        protected ProductReadRepositoryInterface $productReadRepo,
        protected ProductWriteRepositoryInterface $productWriteRepo,
        protected CategoryRepositoryInterface $categoryRepo,
        protected ProductImageRepositoryInterface $imageRepo,
        protected ProductAttributeValueRepositoryInterface $attributeValueRepo,
    ) {}

    /**
     * Build snapshot lengkap untuk satu produk.
     *
     * @param int $productId
     * @return array Snapshot array yang siap disimpan ke metadata_snapshot
     */
    public function buildSnapshot(int $productId): array
    {
        // Load produk beserta semua relasi
        $product = $this->productReadRepo->findByIdWithRelations($productId);

        if (!$product) {
            throw new \InvalidArgumentException("Product #{$productId} not found.");
        }

        return $this->buildSnapshotFromModel($product);
    }

    /**
     * Build snapshot dari model Product yang sudah di-load relasinya.
     * Method ini bisa dipanggil langsung jika model sudah ada di memory.
     */
    public function buildSnapshotFromModel(Product $product): array
    {
        // Resolve primary category
        $primaryCategory = $product->categories
            ->firstWhere('pivot.is_primary', true);

        // Build breadcrumbs dari primary category
        $breadcrumbs = [];
        if ($primaryCategory) {
            $breadcrumbs = $this->categoryRepo
                ->getBreadcrumbs($primaryCategory->id)
                ->map(fn($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ])
                ->toArray();
        }

        // Build categories list
        $categories = $product->categories
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
            ])
            ->toArray();

        // Build attributes
        $attributes = $product->attributeValues
            ->filter(fn($av) => $av->attribute !== null)
            ->map(fn($av) => [
                'code' => $av->attribute->code,
                'name' => $av->attribute->name,
                'value' => $av->getDisplayString(),
            ])
            ->values()
            ->toArray();

        // Build images
        $images = $product->images
            ->map(fn($img) => [
                'url' => $img->url,
                'alt_text' => $img->alt_text,
                'is_primary' => $img->is_primary,
            ])
            ->toArray();

        return [
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'name' => $product->name,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'price' => (float) $product->price,
                'rating_avg' => (float) $product->rating_avg,
            ],
            'primary_category' => $primaryCategory ? [
                'id' => $primaryCategory->id,
                'name' => $primaryCategory->name,
                'slug' => $primaryCategory->slug,
            ] : null,
            'breadcrumbs' => $breadcrumbs,
            'categories' => $categories,
            'attributes' => $attributes,
            'images' => $images,
            'sync' => [
                'version' => $product->metadata_version,
                'last_synced_at' => now()->toDateTimeString(),
            ],
        ];
    }

    /**
     * Rebuild snapshot untuk satu produk: build → save → increment version.
     *
     * @return array The rebuilt snapshot
     */
    public function rebuildSnapshot(int $productId): array
    {
        $product = $this->productReadRepo->findById($productId);

        if (!$product) {
            throw new \InvalidArgumentException("Product #{$productId} not found.");
        }

        // Increment version dulu agar snapshot punya version terbaru
        $newVersion = $this->productWriteRepo->incrementVersion($product);

        // Build snapshot (reload product dengan relasi fresh)
        $snapshot = $this->buildSnapshot($productId);

        // Update version di snapshot
        $snapshot['sync']['version'] = $newVersion;

        // Simpan snapshot ke database
        $this->productWriteRepo->updateSnapshot($product, $snapshot);

        return $snapshot;
    }

    /**
     * Rebuild snapshot untuk banyak produk (batch).
     * Digunakan oleh RebuildProductSnapshotJob.
     *
     * @param array<int> $productIds
     * @return int Jumlah produk yang berhasil di-rebuild
     */
    public function rebuildSnapshotBatch(array $productIds): int
    {
        $count = 0;

        foreach ($productIds as $productId) {
            try {
                $this->rebuildSnapshot($productId);
                $count++;
            } catch (\Throwable $e) {
                // Log error tapi lanjutkan batch
                report($e);
            }
        }

        return $count;
    }
}
