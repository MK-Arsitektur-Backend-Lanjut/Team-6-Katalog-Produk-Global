<?php

namespace App\Services\UserPreference;

use App\Models\UserPreference\ProductViewHistory;
use App\Repositories\Contracts\UserPreference\ProductViewHistoryRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProductViewHistoryService
{
    public function __construct(
        protected ProductViewHistoryRepositoryInterface $histories
    ) {}

    public function getByUserId(int $userId, int $limit = 20): Collection
    {
        return Cache::store('redis')->remember(
            $this->cacheKey($userId, $limit),
            300,
            fn () => $this->histories->getByUserId($userId, $limit)
        );
    }

    public function recordView(int $userId, int $productId): ProductViewHistory
    {
        $history = $this->histories->recordView($userId, $productId);
        $this->forgetCache($userId);

        return $history->load(['product:id,sku,slug,name,price,rating_avg,status']);
    }

    public function clear(int $userId): int
    {
        $deleted = $this->histories->clearByUserId($userId);
        $this->forgetCache($userId);

        return $deleted;
    }

    private function forgetCache(int $userId): void
    {
        Cache::store('redis')->forget($this->cacheKey($userId, 20));
    }

    private function cacheKey(int $userId, int $limit): string
    {
        return "user:{$userId}:view_history:limit:{$limit}";
    }
}
