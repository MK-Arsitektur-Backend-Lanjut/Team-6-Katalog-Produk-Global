<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductAttributeValue;
use App\Models\Catalog\Attribute;
use App\Repositories\Contracts\Catalog\CategoryRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductAttributeValueRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductImageRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductWriteRepositoryInterface;
use App\Services\Catalog\ProductSnapshotService;
use Illuminate\Database\Eloquent\Collection;

it('builds a proper snapshot array from relations', function () {
    $readRepo = mock(ProductReadRepositoryInterface::class);
    $writeRepo = mock(ProductWriteRepositoryInterface::class);
    $categoryRepo = mock(CategoryRepositoryInterface::class);
    $imageRepo = mock(ProductImageRepositoryInterface::class);
    $attrValRepo = mock(ProductAttributeValueRepositoryInterface::class);

    $service = new ProductSnapshotService($readRepo, $writeRepo, $categoryRepo, $imageRepo, $attrValRepo);

    $product = new Product([
        'id' => 5,
        'sku' => 'SKU5',
        'slug' => 'p5',
        'name' => 'P5 Name',
        'short_description' => 'Short',
        'description' => 'Long',
        'price' => 15000,
        'rating_avg' => 4.5,
        'metadata_version' => 2,
    ]);

    // Mock categories
    $category = new Category(['id' => 1, 'name' => 'Cat 1', 'slug' => 'cat-1']);
    // Fake pivot for categories
    $category->setRelation('pivot', (object)['is_primary' => true]);
    $product->setRelation('categories', collect([$category]));

    // Mock breadcrumbs
    $categoryRepo->shouldReceive('getBreadcrumbs')->with(1)->once()->andReturn(collect([
        new Category(['id' => 1, 'name' => 'Cat 1', 'slug' => 'cat-1'])
    ]));

    // Mock attributes
    $attribute = new Attribute(['code' => 'brand', 'name' => 'Brand']);
    $attrValue = new ProductAttributeValue(['display_value' => 'Samsung']);
    $attrValue->setRelation('attribute', $attribute);
    $product->setRelation('attributeValues', collect([$attrValue]));

    // Mock images
    $product->setRelation('images', collect([]));

    $snapshot = $service->buildSnapshotFromModel($product);

    expect($snapshot['product']['sku'])->toEqual('SKU5')
        ->and($snapshot['primary_category']['id'])->toEqual(1)
        ->and($snapshot['attributes'][0]['code'])->toEqual('brand')
        ->and($snapshot['attributes'][0]['value'])->toEqual('Samsung')
        ->and($snapshot['sync']['version'])->toEqual(2);
});
