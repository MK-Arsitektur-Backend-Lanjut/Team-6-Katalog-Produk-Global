<?php

namespace App\Repositories\Contracts\Catalog;

use App\Models\Catalog\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface untuk operasi repository kategori.
 * Mencakup CRUD, hierarki (tree/breadcrumbs/ancestors/descendants),
 * dan manajemen closure table.
 */
interface CategoryRepositoryInterface
{
    /**
     * Cari kategori berdasarkan ID.
     */
    public function findById(int $id): ?Category;

    /**
     * Cari kategori berdasarkan slug.
     */
    public function findBySlug(string $slug): ?Category;

    /**
     * Ambil root categories (parent_id = null) yang aktif.
     */
    public function getRoots(): Collection;

    /**
     * Ambil seluruh tree kategori (root + recursive children).
     * Hanya kategori aktif.
     */
    public function getTree(): Collection;

    /**
     * Ambil semua ancestor dari kategori via closure table.
     * Terurut dari root → kategori itu sendiri (untuk breadcrumbs).
     */
    public function getAncestors(int $categoryId): Collection;

    /**
     * Ambil semua descendant dari kategori via closure table.
     */
    public function getDescendants(int $categoryId): Collection;

    /**
     * Ambil breadcrumbs: ancestor chain dari root ke kategori.
     * Hanya mengembalikan id, name, slug.
     */
    public function getBreadcrumbs(int $categoryId): Collection;

    /**
     * Buat kategori baru.
     */
    public function create(array $data): Category;

    /**
     * Update data kategori.
     */
    public function update(Category $category, array $data): Category;

    /**
     * Soft delete kategori.
     */
    public function delete(Category $category): bool;

    /**
     * Insert closure table entries untuk kategori baru.
     * Harus menambahkan self-reference (depth=0) dan semua ancestor.
     */
    public function insertClosureEntries(Category $category): void;

    /**
     * Rebuild closure table entries saat parent berubah.
     * Menghapus entries lama dan membuat ulang.
     */
    public function rebuildClosureEntries(Category $category): void;

    /**
     * Ambil semua product IDs yang terkait dengan kategori.
     *
     * @return \Illuminate\Support\Collection<int>
     */
    public function getProductIdsByCategory(int $categoryId): \Illuminate\Support\Collection;
}
