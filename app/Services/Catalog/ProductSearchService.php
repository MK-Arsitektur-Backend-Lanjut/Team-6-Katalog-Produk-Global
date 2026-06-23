<?php

namespace App\Services\Catalog;

use App\Repositories\Eloquent\Catalog\EloquentProductSearchRepository;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

/**
 * Service untuk pencarian produk.
 *
 * Optimasi Round 2 — tambahan dari Round 1:
 *
 * [NEW] Cache hasil search:
 *   Endpoint /search adalah satu-satunya search endpoint yang tidak di-cache.
 *   Dengan 1000 VU concurrent memakai keyword/filter serupa, query yang sama
 *   diulang ratusan kali per detik. Setiap hit = 1 query DB berat (LIKE + JOIN).
 *
 *   Solusi: cache hasil paginasi 30 detik berdasarkan hash dari sorted params.
 *   Cache hit = 0 query ke DB → drastis mengurangi beban MySQL.
 *   TTL 30 detik cukup singkat untuk tetap "fresh" dari perspektif UX.
 *
 * [EXISTING] Sanitasi keyword + normalisasi alias + cross-field guard.
 */
class ProductSearchService
{
    /**
     * TTL cache search result dalam detik.
     * 30 detik: cukup untuk absorb burst traffic, tapi tidak stale untuk UX.
     */
    private const SEARCH_CACHE_TTL = 30;

    protected EloquentProductSearchRepository $repo;

    public function __construct(EloquentProductSearchRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Search produk dengan cache layer.
     *
     * @param array $params Parameter yang sudah divalidasi FormRequest
     * @return Paginator
     */
    public function search(array $params): Paginator
    {
        // 1. Normalisasi alias
        $params = $this->normalizeAliases($params);

        // 2. Sanitasi keyword
        if (isset($params['keyword'])) {
            $params['keyword'] = preg_replace('/\s+/', ' ', trim((string) $params['keyword']));
            if ($params['keyword'] === '') {
                unset($params['keyword']);
            }
        }

        // 3. Cross-field guard min_price vs max_price
        if (
            isset($params['min_price'], $params['max_price']) &&
            (float) $params['min_price'] > (float) $params['max_price']
        ) {
            [$params['min_price'], $params['max_price']] = [$params['max_price'], $params['min_price']];
        }

        // 4. Filter hanya key yang diizinkan
        $allowed = ['keyword', 'category_id', 'min_price', 'max_price', 'rating', 'sort_by', 'order', 'page', 'limit'];
        $clean   = [];
        foreach ($allowed as $k) {
            if (isset($params[$k]) && $params[$k] !== '') {
                $clean[$k] = $params[$k];
            }
        }

        // 5. Clamp pagination
        if (isset($clean['limit'])) {
            $clean['limit'] = max(1, min(100, (int) $clean['limit']));
        }
        if (isset($clean['page'])) {
            $clean['page'] = max(1, (int) $clean['page']);
        }

        // 6. Cache search result
        return $this->searchWithCache($clean);
    }

    /**
     * Eksekusi search dengan Redis cache.
     *
     * Cache key dibuat dari ksort() params sehingga urutan param tidak mempengaruhi key.
     * Contoh: ?keyword=phone&sort=price_asc dan ?sort=price_asc&keyword=phone
     * menghasilkan cache key yang SAMA.
     *
     * Kenapa cache search result (bukan hanya query):
     * - 1000 VU dengan keyword 'Samsung', 'Apple', 'phone', dll = sangat banyak duplikat
     * - Tanpa cache: setiap request = 1 query LIKE ke MySQL (full-scan jika no fulltext)
     * - Dengan cache: request ke-2 dst = 0 query DB, langsung dari Redis (< 1ms)
     *
     * @param array $clean Params yang sudah dinormalisasi
     * @return Paginator
     */
    private function searchWithCache(array $clean): Paginator
    {
        // Sort params agar urutan tidak mempengaruhi key
        ksort($clean);
        $cacheKey = 'catalog:search:results:' . md5(serialize($clean));

        /**
         * Cache::remember() di sini menyimpan seluruh object Paginator yang sudah
         * di-serialize. Karena SimplePaginator adalah plain PHP object yang serializable,
         * ini aman.
         *
         * Alternatif yang lebih robust adalah cache array hasil ->items() saja,
         * tapi itu membutuhkan perubahan lebih besar di controller.
         */
        $cached = Cache::store('redis')->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->repo->search($clean);

        // Simpan ke cache. Jika serialisasi gagal (edge case), lewati saja
        try {
            Cache::store('redis')->put($cacheKey, $result, self::SEARCH_CACHE_TTL);
        } catch (\Throwable) {
            // Jika cache gagal, tetap kembalikan hasil DB tanpa error
        }

        return $result;
    }

    /**
     * Normalisasi alias parameter:
     * - 'sort' (shorthand) → 'sort_by' + 'order'
     * - 'min_rating' → 'rating'
     */
    protected function normalizeAliases(array $params): array
    {
        if (isset($params['min_rating']) && !isset($params['rating'])) {
            $params['rating'] = $params['min_rating'];
        }

        if (!empty($params['sort']) && empty($params['sort_by'])) {
            [$sortBy, $order] = match ($params['sort']) {
                'price_asc'   => ['price', 'asc'],
                'price_desc'  => ['price', 'desc'],
                'rating_desc' => ['rating', 'desc'],
                'rating_asc'  => ['rating', 'asc'],
                default       => ['latest', 'desc'],
            };

            $params['sort_by'] = $sortBy;
            $params['order']   = $params['order'] ?? $order;
        }

        return $params;
    }
}
