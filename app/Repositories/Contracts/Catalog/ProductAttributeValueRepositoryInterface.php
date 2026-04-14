<?php

namespace App\Repositories\Contracts\Catalog;

use App\Models\Catalog\ProductAttributeValue;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface untuk operasi repository nilai atribut produk (EAV values).
 * Menangani upsert, delete, dan sync nilai atribut per produk.
 */
interface ProductAttributeValueRepositoryInterface
{
    /**
     * Ambil semua nilai atribut untuk produk tertentu.
     * Eager load relasi attribute dan option.
     */
    public function getByProduct(int $productId): Collection;

    /**
     * Upsert nilai atribut produk (insert or update).
     * Jika sudah ada (product_id + attribute_id), update. Jika belum, insert.
     */
    public function upsertValue(int $productId, int $attributeId, array $data): ProductAttributeValue;

    /**
     * Hapus semua nilai atribut untuk produk tertentu.
     * Return jumlah row yang dihapus.
     */
    public function deleteByProduct(int $productId): int;

    /**
     * Hapus nilai atribut tertentu untuk produk.
     * Digunakan saat attribute di-unassign dari kategori.
     *
     * @param array<int> $attributeIds
     */
    public function deleteByProductAndAttributes(int $productId, array $attributeIds): int;

    /**
     * Sync semua nilai atribut produk.
     * Menghapus yang tidak ada di $values dan upsert sisanya.
     *
     * @param array $values Format: [['attribute_id' => int, ...data], ...]
     */
    public function syncValues(int $productId, array $values): void;
}
