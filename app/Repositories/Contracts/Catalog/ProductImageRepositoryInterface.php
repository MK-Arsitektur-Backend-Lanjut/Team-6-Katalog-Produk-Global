<?php

namespace App\Repositories\Contracts\Catalog;

use App\Models\Catalog\ProductImage;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface untuk operasi repository gambar produk.
 */
interface ProductImageRepositoryInterface
{
    /**
     * Ambil semua gambar produk, terurut berdasarkan position.
     */
    public function getByProduct(int $productId): Collection;

    /**
     * Buat gambar baru untuk produk.
     */
    public function create(array $data): ProductImage;

    /**
     * Update data gambar.
     */
    public function update(ProductImage $image, array $data): ProductImage;

    /**
     * Hapus satu gambar.
     */
    public function delete(ProductImage $image): bool;

    /**
     * Hapus semua gambar produk. Return jumlah row yang dihapus.
     */
    public function deleteByProduct(int $productId): int;

    /**
     * Reorder gambar produk berdasarkan array ID yang sudah diurutkan.
     *
     * @param array<int> $orderedIds IDs gambar dalam urutan baru.
     */
    public function reorder(int $productId, array $orderedIds): void;
}
