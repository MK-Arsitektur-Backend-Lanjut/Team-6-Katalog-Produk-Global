<?php

use App\Services\Catalog\CatalogCacheService;
use Illuminate\Support\Facades\Cache;

it('generates correct cache keys', function () {
    $service = new CatalogCacheService();

    expect($service->productSlugKey('iphone-13'))->toEqual('catalog:product:slug:iphone-13')
        ->and($service->productIdKey(10))->toEqual('catalog:product:id:10')
        ->and($service->categoryTreeKey())->toEqual('catalog:category:tree');
});

it('can get and put to cache', function () {
    $service = new CatalogCacheService();

    Cache::shouldReceive('store')
        ->with('redis')
        ->andReturnSelf();

    Cache::shouldReceive('put')
        ->once()
        ->with('test_key', 'test_value', 3600);

    Cache::shouldReceive('get')
        ->once()
        ->with('test_key')
        ->andReturn('test_value');

    $service->put('test_key', 'test_value');
    $value = $service->get('test_key');

    expect($value)->toEqual('test_value');
});

it('invalidates product correctly', function () {
    $service = new CatalogCacheService();

    Cache::shouldReceive('store')
        ->with('redis')
        ->andReturnSelf();

    Cache::shouldReceive('forget')
        ->with('catalog:product:id:5')
        ->once();

    Cache::shouldReceive('forget')
        ->with('catalog:product:slug:samsung-s22')
        ->once();

    $service->invalidateProduct(5, 'samsung-s22');
});
