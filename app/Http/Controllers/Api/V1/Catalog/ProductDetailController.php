<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\ProductDetailResource;
use App\Services\Catalog\ProductDetailService;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

class ProductDetailController extends Controller
{
    public function __construct(
        protected ProductDetailService $productDetailService,
    ) {}

    #[OA\Get(
        path: '/api/v1/catalog/products',
        operationId: 'getProducts',
        summary: 'Get all active products (offset pagination)',
        tags: ['Public Products'],
        description: 'Returns paginated active products list. Max 10000 items per page. Results are cached in Redis.',
    )]
    #[OA\Parameter(name: 'per_page', description: 'Items per page (max 10000)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15))]
    #[OA\Parameter(name: 'page', description: 'Page number', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 10000);
        $page = max((int) $request->query('page', 1), 1);

        $paginatedData = $this->productDetailService->paginate($perPage, $page);

        return response()->json($paginatedData);
    }

    #[OA\Get(
        path: '/api/v1/catalog/products/cursor',
        operationId: 'getProductsCursor',
        summary: 'Get all active products (cursor pagination)',
        tags: ['Public Products'],
        description: 'Cursor-based pagination — efficient for iterating large datasets (10,000+ products). Does not use OFFSET so performance is constant across all pages.',
    )]
    #[OA\Parameter(name: 'per_page', description: 'Items per page (max 10000)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 100))]
    #[OA\Parameter(name: 'cursor', description: 'Cursor from previous response', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function cursorIndex(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 100), 10000);

        $data = $this->productDetailService->cursorPaginate($perPage);

        return response()->json($data);
    }

    #[OA\Get(
        path: '/api/v1/catalog/products/{slug}',
        operationId: 'getProductBySlug',
        summary: 'Get product detail by slug',
        tags: ['Public Products'],
        description: 'Returns full product detail snapshot',
    )]
    #[OA\Parameter(name: 'slug', description: 'Product Slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Product not found')]
    public function showBySlug(string $slug): JsonResponse
    {
        $data = $this->productDetailService->getBySlug($slug);

        if (!$data) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }

        return response()->json([
            'data' => new ProductDetailResource($data),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/catalog/products/id/{id}',
        operationId: 'getProductById',
        summary: 'Get product detail by ID',
        tags: ['Public Products'],
        description: 'Returns full product detail snapshot',
    )]
    #[OA\Parameter(name: 'id', description: 'Product ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Product not found')]
    public function showById(int $id): JsonResponse
    {
        $data = $this->productDetailService->getById($id);

        if (!$data) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }

        return response()->json([
            'data' => new ProductDetailResource($data),
        ]);
    }
}
