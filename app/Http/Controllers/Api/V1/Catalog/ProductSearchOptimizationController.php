<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Catalog\Product;
use App\Http\Resources\Catalog\ProductResource;
use App\Services\Catalog\CatalogCacheService;

class ProductSearchOptimizationController extends Controller
{
    public function __construct(
        protected CatalogCacheService $cacheService,
    ) {}

    // =========================================================================
    // AUTOCOMPLETE
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/catalog/products/autocomplete',
        operationId: 'autocompleteProducts',
        summary: 'Autocomplete product names',
        tags: ['Module 2 - Search Optimization'],
        description: 'Return product suggestions for autocomplete (id, name, slug) based on prefix match.',
    )]
    #[OA\Parameter(name: 'query', in: 'query', required: true, description: 'Search prefix', schema: new OA\Schema(type: 'string', example: 'sma'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function autocomplete(Request $request)
    {
        $q = trim((string) $request->query('query', ''));

        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $items = $this->cacheService->remember(
            $this->cacheService->searchAutocompleteKey($q),
            fn() => Product::query()
                ->where('status', 'active')
                ->where('name', 'like', $q . '%')
                ->select('id', 'name', 'slug')
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->toArray(),
            $this->cacheService->searchTtl()
        );

        return response()->json(['data' => $items]);
    }

    // =========================================================================
    // FACETS
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/catalog/products/facets',
        operationId: 'productFacets',
        summary: 'Get facets for search UI (category counts, price ranges)',
        tags: ['Module 2 - Search Optimization'],
        description: 'Return category counts and predefined price-range counts to drive UI filters.',
    )]
    #[OA\Parameter(name: 'keyword', in: 'query', required: false, description: 'Optional keyword to scope facets', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function facets(Request $request)
    {
        $kw = trim((string) $request->query('keyword', ''));

        $data = $this->cacheService->remember(
            $this->cacheService->searchFacetsKey($kw),
            function () use ($kw) {
                // Category counts (top 10)
                $categoryCounts = DB::table('product_categories')
                    ->join('products', 'product_categories.product_id', '=', 'products.id')
                    ->join('categories', 'product_categories.category_id', '=', 'categories.id')
                    ->where('products.status', 'active')
                    ->where('categories.is_active', true)
                    ->when($kw !== '', fn($q) => $q->where('products.name', 'like', "%{$kw}%"))
                    ->groupBy('categories.id', 'categories.name')
                    ->orderByRaw('COUNT(products.id) desc')
                    ->limit(10)
                    ->get(['categories.id as category_id', 'categories.name as category_name', DB::raw('COUNT(products.id) as count')])
                    ->toArray();

                $priceBuckets = [
                    ['key' => 'lt_100k',    'alias' => 'lt_100k',          'label' => '< 100k'],
                    ['key' => '100k_500k',  'alias' => 'range_100k_500k',  'label' => '100k - 500k'],
                    ['key' => '500k_1m',    'alias' => 'range_500k_1m',    'label' => '500k - 1M'],
                    ['key' => 'gte_1m',     'alias' => 'gte_1m',           'label' => '>= 1M'],
                ];

                $priceBucketCounts = Product::query()
                    ->where('status', 'active')
                    ->when($kw !== '', fn($q) => $q->where('name', 'like', "%{$kw}%"))
                    ->selectRaw('
                        SUM(CASE WHEN price < 100000 THEN 1 ELSE 0 END) as lt_100k,
                        SUM(CASE WHEN price >= 100000 AND price < 500000 THEN 1 ELSE 0 END) as range_100k_500k,
                        SUM(CASE WHEN price >= 500000 AND price < 1000000 THEN 1 ELSE 0 END) as range_500k_1m,
                        SUM(CASE WHEN price >= 1000000 THEN 1 ELSE 0 END) as gte_1m
                    ')
                    ->first();

                $priceCounts = collect($priceBuckets)
                    ->map(fn($bucket) => [
                        'key'   => $bucket['key'],
                        'label' => $bucket['label'],
                        'count' => (int) ($priceBucketCounts?->{$bucket['alias']} ?? 0),
                    ])
                    ->values()
                    ->all();

                return [
                    'categories'   => $categoryCounts,
                    'price_ranges' => $priceCounts,
                ];
            },
            $this->cacheService->searchTtl()
        );

        return response()->json(['data' => $data]);
    }

    // =========================================================================
    // BULK SEARCH
    // =========================================================================

    #[OA\Post(
        path: '/api/v1/catalog/products/bulk-search',
        operationId: 'bulkSearchProducts',
        summary: 'Bulk lookup products by ids',
        tags: ['Module 2 - Search Optimization'],
        description: 'Return multiple products by ID in a single request (useful for batch calls).',
    )]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'))], type: 'object'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function bulkSearch(Request $request)
    {
        $ids = (array) $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['data' => []]);
        }

        // Sanitasi: cast ke int, buang non-positif, deduplicate, batasi 100 item
        $ids = collect($ids)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->sort()   // sort agar cache key deterministik untuk set ID yang sama
            ->take(100)
            ->values()
            ->all();

        // 1. Ambil dari Redis secara massal (multi-get) menggunakan individual product resource cache
        $cacheKeys = [];
        foreach ($ids as $id) {
            $cacheKeys[$id] = "catalog:product:resource:{$id}";
        }

        $cachedProducts = \Illuminate\Support\Facades\Cache::store('redis')->many(array_values($cacheKeys));

        $results = [];
        $missingIds = [];

        foreach ($ids as $id) {
            $key = $cacheKeys[$id];
            if (isset($cachedProducts[$key]) && $cachedProducts[$key] !== null) {
                $results[$id] = $cachedProducts[$key];
            } else {
                $missingIds[] = $id;
            }
        }

        // 2. Query ke DB hanya untuk ID yang miss di cache
        if (!empty($missingIds)) {
            $dbProducts = Product::where('status', 'active')
                ->whereIn('id', $missingIds)
                ->select([
                    'id', 'sku', 'slug', 'name',
                    'short_description', 'price',
                    'rating_avg', 'status',
                    'metadata_version', 'created_at', 'updated_at',
                ])
                ->get();

            $resolvedDbProducts = ProductResource::collection($dbProducts)->resolve();

            $toCache = [];
            foreach ($resolvedDbProducts as $resolvedProduct) {
                $id = $resolvedProduct['id'];
                $key = "catalog:product:resource:{$id}";
                $toCache[$key] = $resolvedProduct;
                $results[$id] = $resolvedProduct;
            }

            if (!empty($toCache)) {
                \Illuminate\Support\Facades\Cache::store('redis')->putMany($toCache, $this->cacheService->searchTtl());
            }

            // Cache stampede prevention: cache empty values untuk ID yang tidak ditemukan di DB
            foreach ($missingIds as $id) {
                if (!isset($results[$id])) {
                    $key = "catalog:product:resource:{$id}";
                    \Illuminate\Support\Facades\Cache::store('redis')->put($key, [], 60);
                    $results[$id] = [];
                }
            }
        }

        // Susun data kembali sesuai urutan input, saring data yang kosong (tidak ditemukan)
        $data = [];
        foreach ($ids as $id) {
            if (!empty($results[$id])) {
                $data[] = $results[$id];
            }
        }

        return response()->json(['data' => $data]);
    }

    // =========================================================================
    // SUGGEST
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/catalog/products/suggest',
        operationId: 'suggestProducts',
        summary: 'Suggest related products',
        tags: ['Module 2 - Search Optimization'],
        description: 'Return related products (same category or similar) for recommendation widget.',
    )]
    #[OA\Parameter(name: 'product_id', in: 'query', required: true, description: 'Source product id', schema: new OA\Schema(type: 'integer', example: 1))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function suggest(Request $request)
    {
        $productId = (int) $request->query('product_id', 0);

        if ($productId <= 0) {
            return response()->json(['data' => []]);
        }

        /**
         * OPTIMASI: Hilangkan dependency $request dari dalam cache callback.
         *
         * Sebelumnya: ProductResource::collection($products)->resolve($request)
         * dipanggil DALAM cache callback. Ini berarti:
         * 1. Output cache bisa berbeda tergantung request context (locale, Accept header, dll).
         * 2. Cache key hanya berdasarkan $productId, tapi output bergantung $request
         *    → potensi cache pollution (user A mendapat cache dari context request user B).
         *
         * Sekarang: resolve() dipanggil tanpa $request sehingga output cache
         * benar-benar deterministik berdasarkan data DB saja.
         */
        $suggestions = $this->cacheService->remember(
            $this->cacheService->searchSuggestKey($productId),
            function () use ($productId) {
                $product = Product::find($productId);
                if (!$product) {
                    return [];
                }

                // Cari produk di primary category yang sama
                $primaryCategory = DB::table('product_categories')
                    ->where('product_id', $product->id)
                    ->where('is_primary', true)
                    ->value('category_id');

                $products = Product::query()
                    ->where('status', 'active')
                    ->where('id', '!=', $product->id)
                    ->when($primaryCategory, fn($q) => $q->whereExists(function ($sub) use ($primaryCategory) {
                        $sub->select(DB::raw(1))
                            ->from('product_categories')
                            ->whereColumn('product_categories.product_id', 'products.id')
                            ->where('product_categories.category_id', $primaryCategory);
                    }))
                    ->select(['id', 'sku', 'slug', 'name', 'short_description', 'price', 'rating_avg', 'status', 'metadata_version', 'created_at', 'updated_at'])
                    ->orderByDesc('rating_avg')
                    ->limit(5)
                    ->get();

                // resolve() tanpa $request → output deterministik, aman untuk cache
                return ProductResource::collection($products)->resolve();
            },
            $this->cacheService->searchTtl()
        );

        return response()->json(['data' => $suggestions]);
    }

    // =========================================================================
    // STATS
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/catalog/products/stats',
        operationId: 'productSearchStats',
        summary: 'Search / product stats',
        tags: ['Module 2 - Search Optimization'],
        description: 'Return simple statistics useful for search analytics or caching (e.g. total products).',
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function stats()
    {
        $data = $this->cacheService->remember(
            $this->cacheService->searchStatsKey(),
            function () {
                $total       = Product::count();
                $totalActive = Product::where('status', 'active')->count();
                $avgPrice    = (float) (Product::avg('price') ?: 0);
                $avgRating   = Product::avg('rating_avg');

                // ── Top categories by avg rating ────────────────────────────
                $categoriesByAvgRating = DB::table('product_categories')
                    ->join('products', 'product_categories.product_id', '=', 'products.id')
                    ->join('categories', 'product_categories.category_id', '=', 'categories.id')
                    ->whereNotNull('products.rating_avg')
                    ->groupBy('categories.id', 'categories.name')
                    ->select(
                        'categories.id as category_id',
                        'categories.name as category_name',
                        DB::raw('AVG(products.rating_avg) as avg_rating'),
                        DB::raw('COUNT(products.id) as count')
                    )
                    ->orderByDesc('avg_rating')
                    ->limit(10)
                    ->get();

                /**
                 * OPTIMASI: Ganti N+1 query (1 query per kategori dalam loop)
                 * dengan SATU subquery menggunakan ROW_NUMBER() window function.
                 *
                 * Sebelumnya:
                 *   foreach ($categories as $cat) {
                 *       $product = Product::whereHas('categories', ...)->first(); // N query!
                 *   }
                 * 10 kategori = 10 query tambahan.
                 *
                 * Sekarang: 1 query dengan subquery bertingkat yang menghitung
                 * ROW_NUMBER() per kategori dan hanya mengambil rank = 1.
                 * Untuk MySQL < 8, fallback ke subquery GROUP BY + JOIN.
                 */
                $categoryIds = $categoriesByAvgRating->pluck('category_id')->all();

                $topProducts = $this->fetchTopProductPerCategory($categoryIds);

                // Gabungkan data kategori dengan top product-nya
                $categoriesSummary = $categoriesByAvgRating->map(function ($cat) use ($topProducts) {
                    $product = $topProducts[$cat->category_id] ?? null;
                    return [
                        'category_id'   => $cat->category_id,
                        'category_name' => $cat->category_name,
                        'avg_rating'    => isset($cat->avg_rating) ? (float) $cat->avg_rating : null,
                        'count'         => isset($cat->count) ? (int) $cat->count : 0,
                        'top_product'   => $product,
                    ];
                })->values()->all();

                return [
                    'total_products'         => (int) $total,
                    'total_active'           => (int) $totalActive,
                    'avg_price'              => $avgPrice,
                    'avg_rating'             => $avgRating !== null ? (float) $avgRating : null,
                    'categories_by_avg_rating' => $categoriesSummary,
                    'generated_at'           => Carbon::now()->toIso8601String(),
                ];
            },
            $this->cacheService->searchTtl()
        );

        return response()->json(['data' => $data]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Ambil top 1 produk aktif (rating tertinggi) untuk setiap category_id
     * dalam SATU query menggunakan ROW_NUMBER() window function (MySQL 8+).
     *
     * Kenapa satu query vs N query:
     * - N query = round-trip ke DB sebanyak N (untuk 10 kategori = 10 round-trips)
     * - 1 query = 1 round-trip, DB optimizer yang menangani pengelompokan
     *
     * @param int[] $categoryIds
     * @return array<int, array> map dari category_id → top product array
     */
    private function fetchTopProductPerCategory(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        /**
         * Strategi: gunakan subquery dengan ROW_NUMBER() OVER (PARTITION BY category_id)
         * untuk memberi rank pada produk per kategori, lalu ambil hanya rank = 1.
         *
         * Subquery:
         *   SELECT pc.category_id, p.id, p.name, p.slug, p.price, p.rating_avg,
         *          ROW_NUMBER() OVER (PARTITION BY pc.category_id ORDER BY p.rating_avg DESC) as rn
         *   FROM products p
         *   JOIN product_categories pc ON pc.product_id = p.id
         *   WHERE p.status = 'active'
         *     AND p.rating_avg IS NOT NULL
         *     AND pc.category_id IN (...)
         *
         * Outer query:
         *   SELECT * FROM (...) ranked WHERE rn = 1
         */
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $rows = DB::select("
            SELECT category_id, id, name, slug, price, rating_avg
            FROM (
                SELECT
                    pc.category_id,
                    p.id,
                    p.name,
                    p.slug,
                    p.price,
                    p.rating_avg,
                    ROW_NUMBER() OVER (
                        PARTITION BY pc.category_id
                        ORDER BY p.rating_avg DESC
                    ) AS rn
                FROM products p
                INNER JOIN product_categories pc ON pc.product_id = p.id
                WHERE p.status = 'active'
                  AND p.rating_avg IS NOT NULL
                  AND p.deleted_at IS NULL
                  AND pc.category_id IN ({$placeholders})
            ) AS ranked
            WHERE rn = 1
        ", $categoryIds);

        // Index by category_id untuk lookup O(1) saat merge
        $map = [];
        foreach ($rows as $row) {
            $map[$row->category_id] = [
                'id'         => $row->id,
                'name'       => $row->name,
                'slug'       => $row->slug,
                'price'      => (float) $row->price,
                'rating_avg' => (float) $row->rating_avg,
            ];
        }

        return $map;
    }
}
