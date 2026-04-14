<?php

use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('returns 404 for non-existent product', function () {
    $response = $this->getJson('/api/v1/catalog/products/unknown-slug');
    $response->assertStatus(404);
});

it('returns formatted snapshot directly from cache or db', function () {
    // Override cache TTL untuk testing kalau perlu
    Cache::spy();

    $snapshotData = [
        'product' => [
            'id' => 1,
            'sku' => 'TEST-01',
            'slug' => 'test-product',
            'name' => 'Test Product',
            'price' => 50000,
        ],
        'primary_category' => null,
        'breadcrumbs' => [],
        'categories' => [],
        'attributes' => [],
        'images' => [],
    ];

    $product = Product::create([
        'sku' => 'TEST-01',
        'slug' => 'test-product',
        'name' => 'Test Product',
        'price' => 50000,
        'status' => 'active',
        'metadata_version' => 1,
        'metadata_snapshot' => $snapshotData,
    ]);

    // Request
    $response = $this->getJson('/api/v1/catalog/products/test-product');

    // Assert
    $response->assertStatus(200)
        ->assertJsonPath('data.product.sku', 'TEST-01')
        ->assertJsonPath('data.metadata_version', 1);

    // Verify cache was set
    Cache::shouldHaveReceived('store')->with('redis');
});
