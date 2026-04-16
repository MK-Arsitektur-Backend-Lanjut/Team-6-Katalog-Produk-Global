<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\ProductDetailResource;
use App\Services\Catalog\ProductDetailService;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

/**
 * Controller untuk public read API detail produk.
 *
 * Thin controller — semua logic ada di ProductDetailService.
 * Hanya menangani 3 endpoint:
 * - GET /api/v1/catalog/products
 * - GET /api/v1/catalog/products/{slug}
 * - GET /api/v1/catalog/products/id/{id}
 */
class ProductDetailController extends Controller
{
    public function __construct(
        protected ProductDetailService $productDetailService,
    ) {}

    #[OA\Get(
        path: '/api/v1/catalog/products',
        operationId: 'getProducts',
        summary: 'Get all active products',
        tags: ['Module 1 - Catalog Metadata'],
        description: 'Returns paginated active products list',
    )]
    #[OA\Parameter(name: 'per_page', description: 'Items per page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        
        $paginatedData = $this->productDetailService->paginate($perPage);

        return response()->json($paginatedData);
    }

    #[OA\Get(
        path: '/api/v1/catalog/products/{slug}',
        operationId: 'getProductBySlug',
        summary: 'Get product detail by slug',
        tags: ['Module 1 - Catalog Metadata'],
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
        tags: ['Module 1 - Catalog Metadata'],
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
