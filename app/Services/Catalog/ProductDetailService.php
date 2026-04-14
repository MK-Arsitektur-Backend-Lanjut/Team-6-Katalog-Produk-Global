<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;

/**
 * Service untuk membaca detail produk (public read API).
 *
 * Mengimplementasikan cache-aside pattern:
 * 1. Cek Redis cache dulu
 * 2. Jika cache miss, ambil metadata_snapshot dari DB (single column read, no JOINs)
 * 3. Simpan ke cache
 * 4. Return response
 *
 * Service ini TIDAK melakukan join atau build snapshot on-the-fly.
 * Data yang dikembalikan sudah siap tampil dari metadata_snapshot.
 */
class ProductDetailService
{
    public function __construct(
        protected ProductReadRepositoryInterface $productReadRepo,
        protected CatalogCacheService $cacheService,
    ) {}

    /**
     * Ambil detail produk berdasarkan slug (untuk public API).
     * Hanya menampilkan produk dengan status active.
     *
     * @return array|null Snapshot data atau null jika tidak ditemukan/inactive
     */
    public function getBySlug(string $slug): ?array
    {
        $cacheKey = $this->cacheService->productSlugKey($slug);

        return $this->cacheService->remember($cacheKey, function () use ($slug) {
            $product = $this->productReadRepo->findActiveBySlug($slug);

            if (!$product || !$product->metadata_snapshot) {
                return null;
            }

            return $this->formatResponse($product);
        });
    }

    /**
     * Ambil detail produk berdasarkan ID (untuk public API).
     * Hanya menampilkan produk dengan status active.
     *
     * @return array|null Snapshot data atau null jika tidak ditemukan/inactive
     */
    public function getById(int $id): ?array
    {
        $cacheKey = $this->cacheService->productIdKey($id);

        return $this->cacheService->remember($cacheKey, function () use ($id) {
            $product = $this->productReadRepo->findActiveById($id);

            if (!$product || !$product->metadata_snapshot) {
                return null;
            }

            return $this->formatResponse($product);
        });
    }

    /**
     * Ambil produk aktif berserta isinya dengan paginasi (untuk public API).
     */
    public function paginate(int $perPage = 15): array
    {
        $paginator = $this->productReadRepo->paginateActive($perPage);

        $data = $paginator->getCollection()->map(function (Product $product) {
            return $this->formatResponse($product);
        })->toArray();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ];
    }

    /**
     * Format response dari model Product.
     *
     * Mengambil data langsung dari metadata_snapshot (JSON column)
     * sehingga tidak perlu join ke tabel lain.
     * Menambahkan metadata_version dari kolom terpisah sebagai safety check.
     */
    protected function formatResponse(Product $product): array
    {
        $snapshot = $product->metadata_snapshot;

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'slug' => $product->slug,
            'name' => $product->name,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'price' => (float) $product->price,
            'rating_avg' => (float) $product->rating_avg,
            'primary_category' => $snapshot['primary_category'] ?? null,
            'breadcrumbs' => $snapshot['breadcrumbs'] ?? [],
            'categories' => $snapshot['categories'] ?? [],
            'attributes' => $snapshot['attributes'] ?? [],
            'images' => $snapshot['images'] ?? [],
            'metadata_version' => $product->metadata_version,
        ];
    }
}
