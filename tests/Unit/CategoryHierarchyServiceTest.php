<?php

use App\Models\Catalog\Category;
use App\Repositories\Contracts\Catalog\CategoryRepositoryInterface;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Catalog\CatalogOutboxService;
use App\Services\Catalog\CategoryHierarchyService;
use Illuminate\Support\Facades\DB;

it('builds path and calls closure table inserts on creation', function () {
    $repo = mock(CategoryRepositoryInterface::class);
    $cache = mock(CatalogCacheService::class);
    $outbox = mock(CatalogOutboxService::class);

    $service = new CategoryHierarchyService($repo, $cache, $outbox);

    $parent = new Category(['id' => 1, 'depth' => 0, 'path' => 'parent']);
    $newChildModel = new Category(['id' => 2, 'parent_id' => 1, 'name' => 'Child', 'slug' => 'child']);

    DB::shouldReceive('transaction')
        ->once()
        ->andReturnUsing(fn($cb) => $cb());

    $repo->shouldReceive('findById')->with(1)->once()->andReturn($parent);
    $repo->shouldReceive('create')->once()->with([
        'parent_id' => 1,
        'name' => 'Child',
        'slug' => 'child',
        'depth' => 1,
        'path' => 'parent/child',
    ])->andReturn($newChildModel);

    $repo->shouldReceive('insertClosureEntries')->once()->with($newChildModel);
    $cache->shouldReceive('invalidateCategoryTree')->once();
    $outbox->shouldReceive('recordCategoryCreated')->once();

    $result = $service->createCategory([
        'parent_id' => 1,
        'name' => 'Child',
        'slug' => 'child',
    ]);

    expect($result->id)->toEqual(2);
});

it('throws exception on circular reference', function () {
    $repo = mock(CategoryRepositoryInterface::class);
    $cache = mock(CatalogCacheService::class);
    $outbox = mock(CatalogOutboxService::class);

    $service = new CategoryHierarchyService($repo, $cache, $outbox);

    $category = new Category(['id' => 1, 'parent_id' => null]);
    $newParent = new Category(['id' => 2, 'depth' => 1]);

    DB::shouldReceive('transaction')
        ->once()
        ->andReturnUsing(fn($cb) => $cb());

    $repo->shouldReceive('findById')->with(2)->once()->andReturn($newParent);

    // Mock descendants so that Category 2 is deemed a descendant of Category 1
    $repo->shouldReceive('getDescendants')->with(1)->once()->andReturn(collect([new Category(['id' => 2])]));

    expect(fn() => $service->updateCategory($category, ['parent_id' => 2]))
        ->toThrow(\InvalidArgumentException::class, 'Cannot set a descendant as parent (circular reference).');
});
