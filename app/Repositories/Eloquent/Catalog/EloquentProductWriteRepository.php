<?php

namespace App\Repositories\Eloquent\Catalog;

use App\Models\Catalog\Product;
use App\Repositories\Contracts\Catalog\ProductWriteRepositoryInterface;

class EloquentProductWriteRepository implements ProductWriteRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function updateSnapshot(Product $product, array $snapshot): bool
    {
        return $product->update([
            'metadata_snapshot' => $snapshot,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * Menggunakan increment() bawaan Eloquent untuk atomic operation.
     * Setelah increment, refresh model untuk mendapatkan versi terbaru.
     */
    public function incrementVersion(Product $product): int
    {
        $product->increment('metadata_version');
        $product->refresh();

        return $product->metadata_version;
    }

    /**
     * {@inheritdoc}
     */
    public function attachCategories(Product $product, array $categories): void
    {
        $product->categories()->attach($categories);
    }

    /**
     * {@inheritdoc}
     *
     * sync() menghapus relasi yang tidak ada di $categories
     * dan menambahkan yang baru. Pivot data (is_primary) ikut diupdate.
     */
    public function syncCategories(Product $product, array $categories): void
    {
        $product->categories()->sync($categories);
    }
}
