<?php

namespace App\Repositories\Eloquent\UserPreference;

use App\Models\UserPreference\Wishlist;
use App\Repositories\Contracts\UserPreference\WishlistRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentWishlistRepository implements WishlistRepositoryInterface
{
    public function getByUserId(int $userId): Collection
    {
        return Wishlist::with(['product:id,sku,slug,name,price,rating_avg,status'])
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function createIfNotExists(int $userId, int $productId): Wishlist
    {
        return Wishlist::firstOrCreate([
            'user_id' => $userId,
            'product_id' => $productId,
        ]);
    }

    public function deleteByUserAndProduct(int $userId, int $productId): bool
    {
        return Wishlist::where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete() > 0;
    }
}
