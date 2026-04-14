<?php

namespace App\Http\Controllers\Api\V1\Internal\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\Catalog\ProductResource;
use App\Services\Catalog\ProductWriteService;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use Illuminate\Http\JsonResponse;

/**
 * Controller internal untuk manajemen produk (create, update).
 *
 * Endpoint:
 * - POST /api/v1/internal/catalog/products
 * - PUT  /api/v1/internal/catalog/products/{id}
 */
class ProductController extends Controller
{
    public function __construct(
        protected ProductWriteService $productWriteService,
        protected ProductReadRepositoryInterface $productReadRepo,
    ) {}

    #[OA\Post(
        path: '/api/v1/internal/catalog/products',
        operationId: 'storeProduct',
        summary: 'Create new product',
        tags: ['Internal Product Management'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'sku', type: 'string', example: 'SKU-123'),
                new OA\Property(property: 'slug', type: 'string', example: 'product-123'),
                new OA\Property(property: 'name', type: 'string', example: 'Product 123'),
                new OA\Property(property: 'price', type: 'number', example: 15000),
                new OA\Property(
                    property: 'categories',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'category_id', type: 'integer', example: 1),
                            new OA\Property(property: 'is_primary', type: 'boolean', example: true),
                        ],
                        type: 'object'
                    )
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 201, description: 'Created')]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->only([
            'sku', 'slug', 'name', 'short_description',
            'description', 'price', 'rating_avg', 'status',
        ]);

        $categories = $request->categoriesPivotFormat();

        $product = $this->productWriteService->createProduct($data, $categories);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => new ProductResource($product),
        ], 201);
    }

    #[OA\Put(
        path: '/api/v1/internal/catalog/products/{id}',
        operationId: 'updateProduct',
        summary: 'Update product',
        tags: ['Internal Product Management'],
    )]
    #[OA\Parameter(name: 'id', description: 'Product ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Updated Product'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 200, description: 'Updated')]
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = $this->productReadRepo->findById($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }

        $data = $request->only([
            'sku', 'slug', 'name', 'short_description',
            'description', 'price', 'rating_avg', 'status',
        ]);

        // Filter out null values (hanya update field yang dikirim)
        $data = array_filter($data, fn($value) => $value !== null);

        $categories = $request->categoriesPivotFormat();

        $product = $this->productWriteService->updateProduct($product, $data, $categories);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => new ProductResource($product),
        ]);
    }
}
