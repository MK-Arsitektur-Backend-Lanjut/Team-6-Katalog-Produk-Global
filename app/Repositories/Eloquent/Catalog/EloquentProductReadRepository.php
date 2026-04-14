<?php

namespace App\Repositories\Eloquent\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductCategory;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class EloquentProductReadRepository implements ProductReadRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function findActiveBySlug(string $slug): ?Product
    {
        return Product::active()
            ->bySlug($slug)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findActiveById(int $id): ?Product
    {
        return Product::active()
            ->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function paginateActive(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Product::active()->latest()->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Product
    {
        return Product::bySlug($slug)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Product
    {
        return Product::find($id);
    }

    /**
     * {@inheritdoc}
     *
     * Eager load semua relasi yang diperlukan untuk build snapshot:
     * - images (terurut position)
     * - categories (dengan pivot is_primary)
     * - attributeValues → attribute, option
     */
    public function findByIdWithRelations(int $id): ?Product
    {
        return Product::with([
            'images',
            'categories',
            'attributeValues.attribute',
            'attributeValues.option',
        ])->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getProductIdsByCategoryId(int $categoryId): SupportCollection
    {
        return ProductCategory::where('category_id', $categoryId)
            ->pluck('product_id');
    }
}
