<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Repository Contracts
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductWriteRepositoryInterface;
use App\Repositories\Contracts\Catalog\CategoryRepositoryInterface;
use App\Repositories\Contracts\Catalog\AttributeRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductAttributeValueRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductImageRepositoryInterface;
use App\Repositories\Contracts\Catalog\OutboxRepositoryInterface;

// Eloquent Implementations
use App\Repositories\Eloquent\Catalog\EloquentProductReadRepository;
use App\Repositories\Eloquent\Catalog\EloquentProductWriteRepository;
use App\Repositories\Eloquent\Catalog\EloquentCategoryRepository;
use App\Repositories\Eloquent\Catalog\EloquentAttributeRepository;
use App\Repositories\Eloquent\Catalog\EloquentProductAttributeValueRepository;
use App\Repositories\Eloquent\Catalog\EloquentProductImageRepository;
use App\Repositories\Eloquent\Catalog\EloquentOutboxRepository;

/**
 * Service Provider khusus Modul Catalog.
 *
 * Mendaftarkan binding antara repository interface dan implementasi Eloquent.
 * Jika di masa depan ingin mengganti implementasi (misal: ke API-based repository
 * atau cache-backed repository), cukup ubah binding di sini.
 */
class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        // Bind semua repository contracts ke implementasi Eloquent
        $this->app->bind(
            ProductReadRepositoryInterface::class,
            EloquentProductReadRepository::class
        );

        $this->app->bind(
            ProductWriteRepositoryInterface::class,
            EloquentProductWriteRepository::class
        );

        $this->app->bind(
            CategoryRepositoryInterface::class,
            EloquentCategoryRepository::class
        );

        $this->app->bind(
            AttributeRepositoryInterface::class,
            EloquentAttributeRepository::class
        );

        $this->app->bind(
            ProductAttributeValueRepositoryInterface::class,
            EloquentProductAttributeValueRepository::class
        );

        $this->app->bind(
            ProductImageRepositoryInterface::class,
            EloquentProductImageRepository::class
        );

        $this->app->bind(
            OutboxRepositoryInterface::class,
            EloquentOutboxRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
