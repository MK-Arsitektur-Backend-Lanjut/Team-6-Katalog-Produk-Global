<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Catalog\ProductSearchService;

class ProductSearchController extends Controller
{
    protected ProductSearchService $service;

    public function __construct(ProductSearchService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/api/v1/catalog/products/search',
        operationId: 'searchProducts',
        summary: 'Search products with filters, sorting and pagination',
        tags: ['Module 2 - Search Optimization'],
        description: 'Search products using keyword, category, price/rating filters, sorting and pagination.',
    )]
    #[OA\Parameter(name: 'keyword', in: 'query', required: false, description: 'Search keyword', schema: new OA\Schema(type: 'string', example: 'smartphone'))]
    #[OA\Parameter(name: 'category_id', in: 'query', required: false, description: 'Filter by category id (will include descendants)', schema: new OA\Schema(type: 'integer', example: 2))]
    #[OA\Parameter(name: 'min_price', in: 'query', required: false, description: 'Minimum price filter (in IDR)', schema: new OA\Schema(type: 'number', format: 'float', example: 100000))]
    #[OA\Parameter(name: 'max_price', in: 'query', required: false, description: 'Maximum price filter (in IDR)', schema: new OA\Schema(type: 'number', format: 'float', example: 500000))]
    #[OA\Parameter(name: 'min_rating', in: 'query', required: false, description: 'Minimum average rating filter (0-5)', schema: new OA\Schema(type: 'number', format: 'float', example: 4.0))]
    #[OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Sort order (price_asc, price_desc, rating_desc, latest)', schema: new OA\Schema(type: 'string', example: 'price_desc'))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1, example: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page (max 100)', schema: new OA\Schema(type: 'integer', default: 15, example: 10))]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 123),
                                new OA\Property(property: 'sku', type: 'string', example: 'SKU-001'),
                                new OA\Property(property: 'slug', type: 'string', example: 'produk-sku-001'),
                                new OA\Property(property: 'name', type: 'string', example: 'LG Product uQXQG - 1'),
                                new OA\Property(property: 'short_description', type: 'string', example: 'Short description here'),
                                new OA\Property(property: 'description', type: 'string', example: 'Full description here'),
                                new OA\Property(property: 'price', type: 'number', format: 'float', example: 4780000.0),
                                new OA\Property(property: 'rating_avg', type: 'number', format: 'float', example: 4.2),
                                new OA\Property(property: 'status', type: 'string', example: 'active'),
                                new OA\Property(property: 'metadata_version', type: 'integer', example: 1),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-04-15T12:00:00Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-04-15T12:00:00Z'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'meta',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'per_page', type: 'integer', example: 10),
                            new OA\Property(property: 'total', type: 'integer', example: 10000),
                            new OA\Property(property: 'last_page', type: 'integer', example: 1000),
                        ]
                    )
                ]
            ),
            example: [
                'data' => [
                    [
                        'id' => 123,
                        'sku' => 'SKU-001',
                        'slug' => 'produk-sku-001',
                        'name' => 'LG Product uQXQG - 1',
                        'short_description' => 'Short description here',
                        'description' => 'Full description here',
                        'price' => 4780000.0,
                        'rating_avg' => 4.2,
                        'status' => 'active',
                        'metadata_version' => 1,
                        'created_at' => '2026-04-15T12:00:00Z',
                        'updated_at' => '2026-04-15T12:00:00Z',
                    ]
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 10,
                    'total' => 10000,
                    'last_page' => 1000,
                ]
            ]
        )
    )]
    public function __invoke(Request $request)
    {
        $paginator = $this->service->search($request->all());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
