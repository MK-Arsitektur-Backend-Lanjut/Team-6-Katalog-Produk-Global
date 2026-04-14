<?php

namespace App\Http\Controllers\Api\V1\Internal\Catalog;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreAttributeRequest;
use App\Http\Requests\Catalog\UpdateAttributeRequest;
use App\Http\Resources\Catalog\AttributeResource;
use App\Repositories\Contracts\Catalog\AttributeRepositoryInterface;
use Illuminate\Http\JsonResponse;

/**
 * Controller internal untuk manajemen atribut (create, update).
 *
 * Endpoint:
 * - POST /api/v1/internal/catalog/attributes
 * - PUT  /api/v1/internal/catalog/attributes/{id}
 */
class AttributeController extends Controller
{
    public function __construct(
        protected AttributeRepositoryInterface $attributeRepo,
    ) {}

    #[OA\Post(
        path: '/api/v1/internal/catalog/attributes',
        operationId: 'storeAttribute',
        summary: 'Create new attribute',
        tags: ['Internal Attribute Management'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'color'),
                new OA\Property(property: 'name', type: 'string', example: 'Warna'),
                new OA\Property(property: 'data_type', type: 'string', example: 'select'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 201, description: 'Created')]
    public function store(StoreAttributeRequest $request): JsonResponse
    {
        $data = $request->only([
            'code', 'name', 'data_type', 'unit',
            'is_required', 'is_filterable', 'validation_rules',
            'default_value', 'is_active',
        ]);

        $attribute = $this->attributeRepo->create($data);

        // Buat options jika ada
        if ($request->has('options')) {
            foreach ($request->input('options') as $index => $option) {
                $attribute->options()->create([
                    'label' => $option['label'],
                    'value' => $option['value'],
                    'sort_order' => $option['sort_order'] ?? $index,
                    'is_active' => true,
                ]);
            }

            $attribute->load('options');
        }

        return response()->json([
            'message' => 'Attribute created successfully.',
            'data' => new AttributeResource($attribute),
        ], 201);
    }

    #[OA\Put(
        path: '/api/v1/internal/catalog/attributes/{id}',
        operationId: 'updateAttribute',
        summary: 'Update existing attribute',
        tags: ['Internal Attribute Management'],
    )]
    #[OA\Parameter(name: 'id', description: 'Attribute ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Updated Warna'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 200, description: 'Updated')]
    public function update(UpdateAttributeRequest $request, int $id): JsonResponse
    {
        $attribute = $this->attributeRepo->findById($id);

        if (!$attribute) {
            return response()->json([
                'message' => 'Attribute not found.',
            ], 404);
        }

        $data = $request->only([
            'code', 'name', 'data_type', 'unit',
            'is_required', 'is_filterable', 'validation_rules',
            'default_value', 'is_active',
        ]);

        $data = array_filter($data, fn($value) => $value !== null);

        $attribute = $this->attributeRepo->update($attribute, $data);

        // Sync options jika diberikan
        if ($request->has('options')) {
            // Hapus options lama dan buat ulang
            $attribute->options()->delete();

            foreach ($request->input('options') as $index => $option) {
                $attribute->options()->create([
                    'label' => $option['label'],
                    'value' => $option['value'],
                    'sort_order' => $option['sort_order'] ?? $index,
                    'is_active' => true,
                ]);
            }

            $attribute->load('options');
        }

        return response()->json([
            'message' => 'Attribute updated successfully.',
            'data' => new AttributeResource($attribute),
        ]);
    }
}
