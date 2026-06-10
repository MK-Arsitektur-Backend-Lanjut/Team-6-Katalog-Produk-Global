<?php

namespace App\Repositories\Eloquent\UserPreference;

use App\Models\UserPreference\ProductViewHistory;
use App\Repositories\Contracts\UserPreference\ProductViewHistoryRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentProductViewHistoryRepository implements ProductViewHistoryRepositoryInterface
{
    public function getByUserId(int $userId, int $limit = 20): Collection
    {
        return ProductViewHistory::with(['product:id,sku,slug,name,price,rating_avg,status'])
            ->where('user_id', $userId)
            ->latest('viewed_at')
            ->limit($limit)
            ->get();
    }

    public function recordView(int $userId, int $productId): ProductViewHistory
    {
        return ProductViewHistory::updateOrCreate(
            [
                'user_id' => $userId,
                'product_id' => $productId,
            ],
            [
                'viewed_at' => now(),
            ]
        );
    }

    public function clearByUserId(int $userId): int
    {
        return ProductViewHistory::where('user_id', $userId)->delete();
    }
}
