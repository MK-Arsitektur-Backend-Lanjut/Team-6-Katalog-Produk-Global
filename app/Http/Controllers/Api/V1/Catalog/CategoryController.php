<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\BreadcrumbResource;
use App\Http\Resources\Catalog\CategoryResource;
use App\Http\Resources\Catalog\CategoryTreeResource;
use App\Services\Catalog\CategoryHierarchyService;
use Illuminate\Http\JsonResponse;

/**
 * Controller untuk public read API kategori.
 *
 * Endpoint:
 * - GET /api/v1/catalog/categories/tree
 * - GET /api/v1/catalog/categories/{slug}
 * - GET /api/v1/catalog/categories/{slug}/breadcrumbs
 */
class CategoryController extends Controller
{
    public function __construct(
        protected CategoryHierarchyService $categoryService,
    ) {}

    #[OA\Get(
        path: '/api/v1/catalog/categories/tree',
        operationId: 'getCategoryTree',
        summary: 'Get category hierarchy tree',
        tags: ['Module 1 - Catalog Metadata'],
        description: 'Returns all categories in a nested tree structure (cached)',
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function tree(): JsonResponse
    {
        $tree = $this->categoryService->getTree();

        return response()->json([
            'data' => CategoryTreeResource::collection($tree),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/catalog/categories/{slug}',
        operationId: 'getCategoryBySlug',
        summary: 'Get category detail by slug',
        tags: ['Module 1 - Catalog Metadata'],
        description: 'Returns the category details',
    )]
    #[OA\Parameter(name: 'slug', description: 'Category Slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Category not found')]
    public function show(string $slug): JsonResponse
    {
        $category = $this->categoryService->findBySlug($slug);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], 404);
        }

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/catalog/categories/{slug}/breadcrumbs',
        operationId: 'getCategoryBreadcrumbs',
        summary: 'Get breadcrumbs for a category',
        tags: ['Module 1 - Catalog Metadata'],
        description: 'Returns an array of categories representing the path from root to this category',
    )]
    #[OA\Parameter(name: 'slug', description: 'Category Slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Category not found')]
    public function breadcrumbs(string $slug): JsonResponse
    {
        $category = $this->categoryService->findBySlug($slug);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], 404);
        }

        $breadcrumbs = $this->categoryService->getBreadcrumbs($category->id);

        return response()->json([
            'data' => BreadcrumbResource::collection($breadcrumbs),
        ]);
    }
}
