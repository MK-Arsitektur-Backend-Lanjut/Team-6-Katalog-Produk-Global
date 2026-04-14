<?php

namespace App\Repositories\Contracts\Catalog;

use App\Models\Catalog\Product;

/**
 * Interface untuk operasi tulis (write) produk.
 * Menangani create, update, delete, dan operasi snapshot.
 */
interface ProductWriteRepositoryInterface
{
    /**
     * Buat produk baru.
     */
    public function create(array $data): Product;

    /**
     * Update data produk.
     */
    public function update(Product $product, array $data): Product;

    /**
     * Soft delete produk.
     */
    public function delete(Product $product): bool;

    /**
     * Update metadata_snapshot JSON pada produk.
     */
    public function updateSnapshot(Product $product, array $snapshot): bool;

    /**
     * Increment metadata_version dan return versi baru.
     */
    public function incrementVersion(Product $product): int;

    /**
     * Attach kategori ke produk.
     *
     * @param array $categories Format: [category_id => ['is_primary' => bool], ...]
     */
    public function attachCategories(Product $product, array $categories): void;

    /**
     * Sync kategori produk (replace semua kategori).
     *
     * @param array $categories Format: [category_id => ['is_primary' => bool], ...]
     */
    public function syncCategories(Product $product, array $categories): void;
}
