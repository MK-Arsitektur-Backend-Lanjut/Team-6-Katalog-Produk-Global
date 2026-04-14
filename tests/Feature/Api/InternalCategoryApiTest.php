<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates category completely via internal API', function () {
    $payload = [
        'name' => 'Root Category',
        'slug' => 'root-category',
    ];

    $response = $this->postJson('/api/v1/internal/catalog/categories', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.slug', 'root-category');

    $this->assertDatabaseHas('categories', ['slug' => 'root-category', 'depth' => 0]);
    $this->assertDatabaseHas('category_closure', ['depth' => 0]); // Self-reference should be created
});

it('prevents setting self as parent on update', function () {
    $category = app(\App\Services\Catalog\CategoryHierarchyService::class)->createCategory([
        'name' => 'Test',
        'slug' => 'test',
    ]);

    $response = $this->putJson("/api/v1/internal/catalog/categories/{$category->id}", [
        'parent_id' => $category->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});
