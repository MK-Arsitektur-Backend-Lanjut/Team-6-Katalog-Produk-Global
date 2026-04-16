<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\Catalog\Product;
use App\Models\Catalog\Category;
use App\Http\Resources\Catalog\ProductResource;

class ProductSearchOptimizationController extends Controller
{
    public function __construct()
    {
    }

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

        $items = Product::query()
            ->where('name', 'like', $q . '%')
            ->select('id', 'name', 'slug')
            ->limit(10)
            ->get();

        return response()->json(['data' => $items]);
    }

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

        $baseQuery = Product::query()->select('products.id')->where('status', 'active');
        if ($kw !== '') {
            $baseQuery->where('name', 'like', "%{$kw}%");
        }

        // Category counts (top 10)
        $categoryCounts = DB::table('product_categories')
            ->join('products', 'product_categories.product_id', '=', 'products.id')
            ->join('categories', 'product_categories.category_id', '=', 'categories.id')
            ->when($kw !== '', fn($q) => $q->where('products.name', 'like', "%{$kw}%"))
            ->groupBy('categories.id', 'categories.name')
            ->orderByRaw('COUNT(products.id) desc')
            ->limit(10)
            ->get(['categories.id as category_id', 'categories.name as category_name', DB::raw('COUNT(products.id) as count')]);

        // Price range buckets
        $priceBuckets = [
            ['key' => 'lt_100k', 'label' => '< 100k', 'min' => 0, 'max' => 100000],
            ['key' => '100k_500k', 'label' => '100k - 500k', 'min' => 100000, 'max' => 500000],
            ['key' => '500k_1m', 'label' => '500k - 1M', 'min' => 500000, 'max' => 1000000],
            ['key' => 'gte_1m', 'label' => '>= 1M', 'min' => 1000000, 'max' => null],
        ];

        $priceCounts = [];
        foreach ($priceBuckets as $b) {
            $q = Product::query()->where('status', 'active');
            if ($kw !== '') $q->where('name', 'like', "%{$kw}%");
            if ($b['max'] === null) {
                $q->where('price', '>=', $b['min']);
            } else {
                $q->whereBetween('price', [$b['min'], $b['max']]);
            }
            $priceCounts[] = [
                'key' => $b['key'],
                'label' => $b['label'],
                'count' => $q->count(),
            ];
        }

        return response()->json([
            'data' => [
                'categories' => $categoryCounts,
                'price_ranges' => $priceCounts,
            ],
        ]);
    }

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

        $products = Product::whereIn('id', $ids)->get();

        return response()->json(['data' => ProductResource::collection($products)]);
    }

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

        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['data' => []]);
        }

        // find products in same primary category if available
        $primaryCategory = DB::table('product_categories')
            ->where('product_id', $product->id)
            ->where('is_primary', true)
            ->value('category_id');

        $suggestions = Product::query()
            ->where('status', 'active')
            ->where('id', '!=', $product->id)
            ->when($primaryCategory, fn($q) => $q->whereExists(function ($sub) use ($primaryCategory) {
                $sub->select(DB::raw(1))
                    ->from('product_categories')
                    ->whereColumn('product_categories.product_id', 'products.id')
                    ->where('product_categories.category_id', $primaryCategory);
            }))
            ->orderByDesc('rating_avg')
            ->limit(5)
            ->get();

        return response()->json(['data' => ProductResource::collection($suggestions)]);
    }

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
        $cacheKey = 'catalog:stats:global';

        $data = Cache::remember($cacheKey, 60, function () {
            $total = Product::count();

            // total active if column exists
            $totalActive = Schema::hasColumn((new Product)->getTable(), 'status')
                ? Product::where('status', 'active')->count()
                : $total;

            // average price and rating if available
            $avgPrice = Product::avg('price') ?: 0;
            $avgRating = Schema::hasColumn((new Product)->getTable(), 'rating_avg')
                ? Product::avg('rating_avg') ?: null
                : null;

            // (we only compute categories by avg rating + top product per category for this endpoint)

            // categories by average rating (top 10)
            $categoriesByAvgRating = DB::table('product_categories')
                ->join('products', 'product_categories.product_id', '=', 'products.id')
                ->join('categories', 'product_categories.category_id', '=', 'categories.id')
                ->whereNotNull('products.rating_avg')
                ->groupBy('categories.id', 'categories.name')
                ->select('categories.id as category_id', 'categories.name as category_name', DB::raw('AVG(products.rating_avg) as avg_rating'), DB::raw('COUNT(products.id) as count'))
                ->orderByDesc('avg_rating')
                ->limit(10)
                ->get();

            // top product (limit 1) per category and build final summary array
            $categoriesSummary = [];
            foreach ($categoriesByAvgRating as $cat) {
                $product = Product::whereHas('categories', function ($q) use ($cat) {
                        $q->where('categories.id', $cat->category_id);
                    })
                    ->when(Schema::hasColumn((new Product)->getTable(), 'status'), fn($q) => $q->where('status', 'active'))
                    ->whereNotNull('rating_avg')
                    ->orderByDesc('rating_avg')
                    ->limit(1)
                    ->first(['id', 'name', 'slug', 'price', 'rating_avg']);

                $categoriesSummary[] = [
                    'category_id' => $cat->category_id,
                    'category_name' => $cat->category_name,
                    'avg_rating' => isset($cat->avg_rating) ? (float) $cat->avg_rating : null,
                    'count' => isset($cat->count) ? (int) $cat->count : 0,
                    'top_product' => $product ? ProductResource::make($product)->resolve() : null,
                ];
            }

            return [
                'total_products' => (int) $total,
                'total_active' => (int) $totalActive,
                'avg_price' => (float) $avgPrice,
                'avg_rating' => $avgRating !== null ? (float) $avgRating : null,
                'categories_by_avg_rating' => $categoriesSummary,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
