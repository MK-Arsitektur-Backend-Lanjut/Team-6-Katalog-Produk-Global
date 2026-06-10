<?php

namespace App\Repositories\Contracts\UserPreference;

use App\Models\UserPreference\ProductViewHistory;
use Illuminate\Support\Collection;

interface ProductViewHistoryRepositoryInterface
{
    public function getByUserId(int $userId, int $limit = 20): Collection;

    public function recordView(int $userId, int $productId): ProductViewHistory;

    public function clearByUserId(int $userId): int;
}
