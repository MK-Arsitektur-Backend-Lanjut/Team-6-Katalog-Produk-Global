<?php

namespace App\Http\Controllers\Api\V1\UserPreference;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserPreference\StoreProductViewHistoryRequest;
use App\Http\Resources\UserPreference\ProductViewHistoryResource;
use App\Services\UserPreference\ProductViewHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductViewHistoryController extends Controller
{
    public function __construct(
        protected ProductViewHistoryService $historyService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 20), 100);
        $histories = $this->historyService->getByUserId(Auth::id(), $limit);

        return response()->json([
            'data' => ProductViewHistoryResource::collection($histories),
            'meta' => [
                'limit' => $limit,
            ],
        ]);
    }

    public function store(StoreProductViewHistoryRequest $request): JsonResponse
    {
        $history = $this->historyService->recordView(
            Auth::id(),
            (int) $request->validated('product_id')
        );

        return response()->json([
            'message' => 'Product view recorded successfully',
            'data' => new ProductViewHistoryResource($history),
        ], 201);
    }

    public function clear(): JsonResponse
    {
        $deleted = $this->historyService->clear(Auth::id());

        return response()->json([
            'message' => 'Product view history cleared',
            'deleted_count' => $deleted,
        ]);
    }
}
