<?php

namespace App\Http\Controllers\Api\V1\Internal\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SyncProductAttributeRequest;
use App\Repositories\Contracts\Catalog\AttributeRepositoryInterface;
use App\Services\Catalog\CategoryHierarchyService;
use App\Services\Catalog\ProductAttributeSyncService;
use Illuminate\Http\JsonResponse;

/**
 * Controller internal untuk sinkronisasi atribut.
 *
 * Endpoint:
 * - PUT  /api/v1/internal/catalog/products/{id}/attributes/sync
 * - POST /api/v1/internal/catalog/categories/{id}/attributes/sync
 */
class ProductAttributeController extends Controller
{
    public function __construct(
        protected ProductAttributeSyncService $syncService,
        protected AttributeRepositoryInterface $attributeRepo,
        protected CategoryHierarchyService $categoryService,
    ) {}

    #[OA\Put(
        path: '/api/v1/internal/catalog/products/{id}/attributes/sync',
        operationId: 'syncProductAttributes',
        summary: 'Sync product inherited attributes',
        tags: ['Internal Attribute Sync'],
        description: 'Sync attributes for a product based on its primary category hierarchy',
    )]
    #[OA\Parameter(name: 'id', description: 'Product ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Synced successfully')]
    public function syncProductAttributes(int $id): JsonResponse
    {
        try {
            $result = $this->syncService->syncProductAttributes($id);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        }

        return response()->json([
            'message' => 'Product attributes synced successfully.',
            'data' => [
                'product_id' => $id,
                'attributes_added' => $result['added'],
                'attributes_removed' => $result['removed'],
                'attributes_retained' => $result['retained'],
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/internal/catalog/categories/{id}/attributes/sync',
        operationId: 'syncCategoryAttributes',
        summary: 'Sync category attribute definitions',
        tags: ['Internal Attribute Sync'],
        description: 'Attach attribute definitions to a category and trigger product re-syncs',
    )]
    #[OA\Parameter(name: 'id', description: 'Category ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'attributes',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'attribute_id', type: 'integer', example: 1),
                            new OA\Property(property: 'is_required', type: 'boolean', example: true),
                        ],
                        type: 'object'
                    )
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 200, description: 'Synced successfully')]
    public function syncCategoryAttributes(SyncProductAttributeRequest $request, int $id): JsonResponse
    {
        $category = $this->categoryService->findById($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], 404);
        }

        // Sync atribut ke kategori
        if ($request->has('attributes')) {
            $pivotData = collect($request->input('attributes'))
                ->mapWithKeys(fn($attr) => [
                    $attr['attribute_id'] => [
                        'is_required' => $attr['is_required'] ?? false,
                        'sort_order' => $attr['sort_order'] ?? 0,
                    ],
                ])
                ->toArray();

            $this->attributeRepo->syncCategoryAttributes($id, $pivotData);
        }

        // Re-sync semua produk yang terkait dengan kategori ini
        $syncedProductIds = $this->syncService->syncCategoryProducts($id);

        return response()->json([
            'message' => 'Category attributes synced successfully.',
            'data' => [
                'category_id' => $id,
                'products_synced' => count($syncedProductIds),
                'product_ids' => $syncedProductIds,
            ],
        ]);
    }
}
