<?php

namespace App\Repositories\Contracts\UserPreference;

use App\Models\UserPreference\Wishlist;
use Illuminate\Support\Collection;

interface WishlistRepositoryInterface
{
    public function getByUserId(int $userId): Collection;

    public function createIfNotExists(int $userId, int $productId): Wishlist;

    public function deleteByUserAndProduct(int $userId, int $productId): bool;
}
