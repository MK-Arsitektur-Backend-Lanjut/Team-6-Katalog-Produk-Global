<?php

namespace App\Http\Controllers\Api\V1\Internal\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\Catalog\CategoryResource;
use App\Services\Catalog\CategoryHierarchyService;
use Illuminate\Http\JsonResponse;

/**
 * Controller internal untuk manajemen kategori (create, update, delete).
 *
 * Endpoint:
 * - POST   /api/v1/internal/catalog/categories
 * - PUT    /api/v1/internal/catalog/categories/{id}
 * - DELETE /api/v1/internal/catalog/categories/{id}
 */
class CategoryController extends Controller
{
    public function __construct(
        protected CategoryHierarchyService $categoryService,
    ) {}

    #[OA\Post(
        path: '/api/v1/internal/catalog/categories',
        operationId: 'storeCategory',
        summary: 'Create new category',
        tags: ['Internal Category Management'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'New Category'),
                new OA\Property(property: 'slug', type: 'string', example: 'new-category'),
                new OA\Property(property: 'parent_id', type: 'integer', example: 1),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 201, description: 'Created')]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = $this->categoryService->createCategory($data);

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    #[OA\Put(
        path: '/api/v1/internal/catalog/categories/{id}',
        operationId: 'updateCategory',
        summary: 'Update category',
        tags: ['Internal Category Management'],
    )]
    #[OA\Parameter(name: 'id', description: 'Category ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Updated Category'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 200, description: 'Updated')]
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->categoryService->findById($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], 404);
        }

        try {
            $category = $this->categoryService->updateCategory($category, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => new CategoryResource($category),
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/internal/catalog/categories/{id}',
        operationId: 'deleteCategory',
        summary: 'Delete category',
        tags: ['Internal Category Management'],
    )]
    #[OA\Parameter(name: 'id', description: 'Category ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Deleted')]
    public function destroy(int $id): JsonResponse
    {
        $category = $this->categoryService->findById($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], 404);
        }

        try {
            $this->categoryService->deleteCategory($category);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
