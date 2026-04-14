<?php

use App\Models\Catalog\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('validates required fields on create product', function () {
    $response = $this->postJson('/api/v1/internal/catalog/products', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['sku', 'slug', 'name', 'price', 'categories']);
});

it('can create product and assign categories', function () {
    $category = \App\Services\Catalog\CategoryHierarchyService::class;
    $categoryRepo = app(\App\Repositories\Contracts\Catalog\CategoryRepositoryInterface::class);

    $cat = $categoryRepo->create([
        'name' => 'Test',
        'slug' => 'test',
        'depth' => 0,
        'path' => 'test',
    ]);
    $categoryRepo->insertClosureEntries($cat);

    $payload = [
        'sku' => 'SKU-001',
        'slug' => 'test-product-01',
        'name' => 'Test Product',
        'price' => 15000,
        'categories' => [
            [
                'category_id' => $cat->id,
                'is_primary' => true
            ]
        ]
    ];

    $response = $this->postJson('/api/v1/internal/catalog/products', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.sku', 'SKU-001');

    $this->assertDatabaseHas('products', ['sku' => 'SKU-001']);
    $this->assertDatabaseHas('catalog_outbox', ['aggregate_type' => 'product']);
});
