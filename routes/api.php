<?php

use Illuminate\Support\Facades\Route;

// Module 3 - User Preferences & Auth Controllers
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\UserPreference\WishlistController;
use App\Http\Controllers\Api\V1\UserPreference\ProductViewHistoryController;

// Public Read API Controllers
use App\Http\Controllers\Api\V1\Catalog\ProductDetailController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController;

// Internal Management API Controllers
use App\Http\Controllers\Api\V1\Internal\Catalog\ProductController;
use App\Http\Controllers\Api\V1\Internal\Catalog\CategoryController as InternalCategoryController;
use App\Http\Controllers\Api\V1\Internal\Catalog\AttributeController;
use App\Http\Controllers\Api\V1\Internal\Catalog\ProductAttributeController;

/*
|--------------------------------------------------------------------------
| API Routes — Modul 1: Catalog Metadata
|--------------------------------------------------------------------------
|
| Semua route di-prefix dengan /api/v1 oleh RouteServiceProvider.
| Dipisahkan menjadi:
| 1. Public Read API — diakses oleh frontend/consumer
| 2. Internal Management API — diakses oleh admin/internal service
|
*/

// =============================================================================
// PUBLIC READ API
// =============================================================================

Route::prefix('v1/catalog')->group(function () {

    // Product Detail (read-only, cache-backed)
    Route::prefix('products')->group(function () {
        // Product Search
        Route::get('search', [\App\Http\Controllers\Api\V1\Catalog\ProductSearchController::class, '__invoke'])
            ->name('catalog.products.search');

        // Search Optimization endpoints (autocomplete, facets, bulk, suggest, stats)
        Route::get('autocomplete', [\App\Http\Controllers\Api\V1\Catalog\ProductSearchOptimizationController::class, 'autocomplete'])
            ->name('catalog.products.autocomplete');

        Route::get('facets', [\App\Http\Controllers\Api\V1\Catalog\ProductSearchOptimizationController::class, 'facets'])
            ->name('catalog.products.facets');

        Route::post('bulk-search', [\App\Http\Controllers\Api\V1\Catalog\ProductSearchOptimizationController::class, 'bulkSearch'])
            ->name('catalog.products.bulkSearch');

        Route::get('suggest', [\App\Http\Controllers\Api\V1\Catalog\ProductSearchOptimizationController::class, 'suggest'])
            ->name('catalog.products.suggest');

        Route::get('stats', [\App\Http\Controllers\Api\V1\Catalog\ProductSearchOptimizationController::class, 'stats'])
            ->name('catalog.products.stats');

        Route::get('/', [ProductDetailController::class, 'index'])
            ->name('catalog.products.index');

        Route::get('cursor', [ProductDetailController::class, 'cursorIndex'])
            ->name('catalog.products.cursor');

        Route::get('id/{id}', [ProductDetailController::class, 'showById'])
            ->where('id', '[0-9]+')
            ->name('catalog.products.showById');

        Route::get('{slug}', [ProductDetailController::class, 'showBySlug'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('catalog.products.showBySlug');
    });

    // Category (read-only, cache-backed)
    Route::prefix('categories')->group(function () {
        Route::get('tree', [CategoryController::class, 'tree'])
            ->name('catalog.categories.tree');

        Route::get('{slug}/breadcrumbs', [CategoryController::class, 'breadcrumbs'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('catalog.categories.breadcrumbs');

        Route::get('{slug}', [CategoryController::class, 'show'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('catalog.categories.show');
    });
});

// =============================================================================
// INTERNAL MANAGEMENT API
// =============================================================================

Route::prefix('v1/internal/catalog')->group(function () {

    // Product Management
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store'])
            ->name('internal.catalog.products.store');

        Route::put('{id}', [ProductController::class, 'update'])
            ->where('id', '[0-9]+')
            ->name('internal.catalog.products.update');

        // Product Attribute Sync
        Route::put('{id}/attributes/sync', [ProductAttributeController::class, 'syncProductAttributes'])
            ->where('id', '[0-9]+')
            ->name('internal.catalog.products.attributes.sync');
    });

    // Category Management
    Route::prefix('categories')->group(function () {
        Route::post('/', [InternalCategoryController::class, 'store'])
            ->name('internal.catalog.categories.store');

        Route::put('{id}', [InternalCategoryController::class, 'update'])
            ->where('id', '[0-9]+')
            ->name('internal.catalog.categories.update');

        Route::delete('{id}', [InternalCategoryController::class, 'destroy'])
            ->where('id', '[0-9]+')
            ->name('internal.catalog.categories.destroy');

        // Category Attribute Sync
        Route::post('{id}/attributes/sync', [ProductAttributeController::class, 'syncCategoryAttributes'])
            ->where('id', '[0-9]+')
            ->name('internal.catalog.categories.attributes.sync');
    });

    // Attribute Management
    Route::prefix('attributes')->group(function () {
        Route::post('/', [AttributeController::class, 'store'])
            ->name('internal.catalog.attributes.store');

        Route::put('{id}', [AttributeController::class, 'update'])
            ->where('id', '[0-9]+')
            ->name('internal.catalog.attributes.update');
    });
});


// =============================================================================
// MODULE 3 — USER PREFERENCES & AUTH
// =============================================================================

Route::prefix('v1/auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])
        ->name('auth.register');

    Route::post('login', [AuthController::class, 'login'])
        ->name('auth.login');

    Route::middleware('jwt.auth')->group(function () {
        Route::get('me', [AuthController::class, 'me'])
            ->name('auth.me');

        Route::post('logout', [AuthController::class, 'logout'])
            ->name('auth.logout');
    });
});

Route::middleware('jwt.auth')->prefix('v1/user')->group(function () {
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index'])
            ->name('user.wishlist.index');

        Route::post('/', [WishlistController::class, 'store'])
            ->name('user.wishlist.store');

        Route::delete('{productId}', [WishlistController::class, 'destroy'])
            ->where('productId', '[0-9]+')
            ->name('user.wishlist.destroy');
    });

    Route::prefix('history/views')->group(function () {
        Route::get('/', [ProductViewHistoryController::class, 'index'])
            ->name('user.history.views.index');

        Route::post('/', [ProductViewHistoryController::class, 'store'])
            ->name('user.history.views.store');

        Route::delete('/', [ProductViewHistoryController::class, 'clear'])
            ->name('user.history.views.clear');
    });
});
