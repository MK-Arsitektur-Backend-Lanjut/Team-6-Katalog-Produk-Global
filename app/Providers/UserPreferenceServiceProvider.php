<?php

namespace App\Providers;

use App\Repositories\Contracts\Auth\UserRepositoryInterface;
use App\Repositories\Contracts\UserPreference\ProductViewHistoryRepositoryInterface;
use App\Repositories\Contracts\UserPreference\WishlistRepositoryInterface;
use App\Repositories\Eloquent\Auth\EloquentUserRepository;
use App\Repositories\Eloquent\UserPreference\EloquentProductViewHistoryRepository;
use App\Repositories\Eloquent\UserPreference\EloquentWishlistRepository;
use Illuminate\Support\ServiceProvider;

class UserPreferenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(WishlistRepositoryInterface::class, EloquentWishlistRepository::class);
        $this->app->bind(ProductViewHistoryRepositoryInterface::class, EloquentProductViewHistoryRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
