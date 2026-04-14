<?php

namespace App\Repositories\Contracts\Catalog;

use App\Models\Catalog\Attribute;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface untuk operasi repository atribut.
 * Mencakup CRUD atribut dan manajemen relasi category-attribute.
 */
interface AttributeRepositoryInterface
{
    /**
     * Cari atribut berdasarkan ID.
     */
    public function findById(int $id): ?Attribute;

    /**
     * Cari atribut berdasarkan code.
     */
    public function findByCode(string $code): ?Attribute;

    /**
     * Buat atribut baru.
     */
    public function create(array $data): Attribute;

    /**
     * Update data atribut.
     */
    public function update(Attribute $attribute, array $data): Attribute;

    /**
     * Ambil atribut yang didefinisikan langsung pada category IDs tertentu.
     * Tidak termasuk inherited dari ancestor.
     *
     * @param array<int> $categoryIds
     */
    public function getByCategoryIds(array $categoryIds): Collection;

    /**
     * Ambil semua atribut yang diwariskan (inherited) untuk sebuah kategori.
     * Menelusuri ancestor chain via closure table dan mengumpulkan semua atribut.
     * Atribut diurutkan dari ancestor terjauh (root) ke kategori sendiri.
     */
    public function getInheritedAttributes(int $categoryId): Collection;

    /**
     * Sync atribut ke kategori (replace semua).
     *
     * @param array $attributes Format: [attribute_id => ['is_required' => bool, 'sort_order' => int], ...]
     */
    public function syncCategoryAttributes(int $categoryId, array $attributes): void;
}
