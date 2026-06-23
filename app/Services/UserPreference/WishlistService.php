<?php

namespace App\Services\UserPreference;

use App\Models\UserPreference\Wishlist;
use App\Repositories\Contracts\UserPreference\WishlistRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WishlistService
{
    public function __construct(
        protected WishlistRepositoryInterface $wishlists
    ) {}

    public function getByUserId(int $userId): Collection
    {
        return Cache::store('redis')->remember(
            $this->cacheKey($userId),
            600,
            fn () => $this->wishlists->getByUserId($userId)
        );
    }

    public function add(int $userId, int $productId): Wishlist
    {
        $wishlist = $this->wishlists->createIfNotExists($userId, $productId);
        $this->forgetCache($userId);

        return $wishlist->load(['product:id,sku,slug,name,price,rating_avg,status']);
    }

    public function remove(int $userId, int $productId): bool
    {
        $deleted = $this->wishlists->deleteByUserAndProduct($userId, $productId);
        $this->forgetCache($userId);

        return $deleted;
    }

    private function forgetCache(int $userId): void
    {
        Cache::store('redis')->forget($this->cacheKey($userId));
    }

    private function cacheKey(int $userId): string
    {
        return "user:{$userId}:wishlist";
    }
}
