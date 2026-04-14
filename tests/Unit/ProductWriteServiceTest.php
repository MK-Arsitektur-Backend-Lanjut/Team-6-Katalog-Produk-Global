<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductWriteRepositoryInterface;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Catalog\CatalogOutboxService;
use App\Services\Catalog\ProductAttributeSyncService;
use App\Services\Catalog\ProductSnapshotService;
use App\Services\Catalog\ProductWriteService;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

it('orchestrates product creation correctly', function () {
    // 1. Arrange Mocks
    $readRepo = mock(ProductReadRepositoryInterface::class);
    $writeRepo = mock(ProductWriteRepositoryInterface::class);
    $snapshotService = mock(ProductSnapshotService::class);
    $attrSyncService = mock(ProductAttributeSyncService::class);
    $cacheService = mock(CatalogCacheService::class);
    $outboxService = mock(CatalogOutboxService::class);

    $service = new ProductWriteService(
        $readRepo, $writeRepo, $snapshotService,
        $attrSyncService, $cacheService, $outboxService
    );

    $inputData = ['name' => 'New Product', 'sku' => 'NP-01', 'slug' => 'new-product'];
    $categories = [1 => ['is_primary' => true]];

    $mockProduct = new Product(['id' => 10] + $inputData);

    // Mock assertions
    DB::shouldReceive('transaction')
        ->once()
        ->andReturnUsing(function ($callback) {
            return $callback();
        });

    $writeRepo->shouldReceive('create')->once()->andReturn($mockProduct);
    $writeRepo->shouldReceive('attachCategories')->once()->with($mockProduct, $categories);

    $attrSyncService->shouldReceive('syncProductAttributes')->once()->with(10);
    $snapshotService->shouldReceive('rebuildSnapshot')->once()->with(10);

    $outboxService->shouldReceive('recordProductCreated')->once()->with(10, \Mockery::any());

    // 2. Act
    $result = $service->createProduct($inputData, $categories);

    // 3. Assert
    expect($result->id)->toEqual(10);
});
