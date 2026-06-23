<?php

namespace App\Http\Controllers\Api\V1\UserPreference;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserPreference\StoreWishlistRequest;
use App\Http\Resources\UserPreference\WishlistResource;
use App\Services\UserPreference\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function __construct(
        protected WishlistService $wishlistService
    ) {}

    public function index(): JsonResponse
    {
        $wishlists = $this->wishlistService->getByUserId(Auth::id());

        return response()->json([
            'data' => WishlistResource::collection($wishlists),
        ]);
    }

    public function store(StoreWishlistRequest $request): JsonResponse
    {
        $wishlist = $this->wishlistService->add(
            Auth::id(),
            (int) $request->validated('product_id')
        );

        return response()->json([
            'message' => 'Product added to wishlist',
            'data' => new WishlistResource($wishlist),
        ], 201);
    }

    public function destroy(int $productId): JsonResponse
    {
        $deleted = $this->wishlistService->remove(Auth::id(), $productId);

        if (! $deleted) {
            return response()->json([
                'message' => 'Wishlist item not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Product removed from wishlist',
        ]);
    }
}
