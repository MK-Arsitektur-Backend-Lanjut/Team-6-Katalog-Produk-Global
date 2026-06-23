<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;

/**
 * Service untuk mengelola cache Redis pada modul Catalog.
 *
 * Mengimplementasikan cache-aside pattern:
 * 1. Read: cek cache → jika miss, ambil dari source → simpan ke cache → return
 * 2. Write: invalidate cache key terkait
 *
 * Cache keys menggunakan prefix 'catalog:' untuk namespace isolation.
 */
class CatalogCacheService
{
    /**
     * Default TTL cache dalam detik (1 jam).
     * Bisa di-override via config('catalog.cache_ttl').
     */
    protected function getTtl(): int
    {
        return (int) config('catalog.cache_ttl', 3600);
    }

    // =========================================================================
    // KEY GENERATORS
    // =========================================================================

    public function productSlugKey(string $slug): string
    {
        return "catalog:product:slug:{$slug}";
    }

    public function productIdKey(int $id): string
    {
        return "catalog:product:id:{$id}";
    }

    public function productListPageKey(int $page, int $perPage): string
    {
        return "catalog:products:list:pp{$perPage}:p{$page}";
    }

    public function categoryTreeKey(): string
    {
        return 'catalog:category:tree';
    }

    public function categoryBreadcrumbsKey(int $categoryId): string
    {
        return "catalog:category:breadcrumbs:{$categoryId}";
    }

    public function categorySlugKey(string $slug): string
    {
        return "catalog:category:slug:{$slug}";
    }

    // =========================================================================
    // CACHE OPERATIONS
    // =========================================================================

    /**
     * Ambil data dari cache. Return null jika miss.
     */
    public function get(string $key): mixed
    {
        return Cache::store('redis')->get($key);
    }

    /**
     * Simpan data ke cache dengan TTL.
     */
    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        Cache::store('redis')->put($key, $value, $ttl ?? $this->getTtl());
    }

    /**
     * Cache-aside: ambil dari cache, jika miss jalankan callback dan simpan hasilnya.
     * Menggunakan Cache::remember() yang sudah atomic di Laravel.
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return Cache::store('redis')->remember(
            $key,
            $ttl ?? $this->getTtl(),
            $callback
        );
    }

    /**
     * Hapus satu cache key.
     */
    public function forget(string $key): bool
    {
        return Cache::store('redis')->forget($key);
    }

    // =========================================================================
    // DOMAIN-SPECIFIC INVALIDATION
    // =========================================================================

    /**
     * Invalidate semua cache yang terkait dengan sebuah produk.
     * Dipanggil saat produk diupdate, kategori berubah, atribut berubah, dll.
     */
    public function invalidateProduct(int $productId, ?string $slug = null): void
    {
        $this->forget($this->productIdKey($productId));

        if ($slug) {
            $this->forget($this->productSlugKey($slug));
        }

        $this->invalidateProductList();
    }

    public function invalidateProductList(): void
    {
        $prefix = config('database.redis.options.prefix', '');
        $pattern = 'catalog:products:list:*';

        try {
            $redis = Cache::store('redis')->getStore()->getRedis()->connection();
            $cursor = null;
            do {
                $result = $redis->scan($cursor, [
                    'match' => $prefix . $pattern,
                    'count' => 100,
                ]);

                if ($result === false) break;

                [$cursor, $keys] = $result;

                if (!empty($keys)) {
                    $redis->del(...$keys);
                }
            } while ($cursor !== 0 && $cursor !== '0');
        } catch (\Throwable $e) {
            // Fallback: jika SCAN gagal, invalidasi tetap berjalan via log
            report($e);
        }
    }

    /**
     * Invalidate cache category tree.
     * Dipanggil saat ada perubahan pada kategori manapun.
     */
    public function invalidateCategoryTree(): void
    {
        $this->forget($this->categoryTreeKey());
    }

    /**
     * Invalidate cache breadcrumbs untuk kategori tertentu.
     */
    public function invalidateCategoryBreadcrumbs(int $categoryId): void
    {
        $this->forget($this->categoryBreadcrumbsKey($categoryId));
    }

    /**
     * Invalidate cache slug kategori.
     */
    public function invalidateCategorySlug(string $slug): void
    {
        $this->forget($this->categorySlugKey($slug));
    }

    /**
     * Invalidate semua cache terkait kategori (tree + breadcrumbs + slug).
     * Digunakan saat kategori di-create/update/delete/move.
     */
    public function invalidateCategory(int $categoryId, ?string $slug = null): void
    {
        $this->invalidateCategoryTree();
        $this->invalidateCategoryBreadcrumbs($categoryId);

        if ($slug) {
            $this->invalidateCategorySlug($slug);
        }
    }
}
