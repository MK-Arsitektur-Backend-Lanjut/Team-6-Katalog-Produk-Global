<?php

namespace App\Repositories\Contracts\Catalog;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface untuk operasi baca (read) produk.
 * Dipisah dari write agar sesuai prinsip CQRS (read/write separation).
 * Read repository fokus pada query yang dioptimasi untuk API publik.
 */
interface ProductReadRepositoryInterface
{
    /**
     * Cari produk aktif berdasarkan slug (untuk public API).
     * Mengembalikan null jika tidak ditemukan atau tidak aktif.
     */
    public function findActiveBySlug(string $slug): ?Product;

    /**
     * Cari produk aktif berdasarkan ID (untuk public API).
     * Mengembalikan null jika tidak ditemukan atau tidak aktif.
     */
    public function findActiveById(int $id): ?Product;

    /**
     * Ambil semua produk aktif dengan paginasi.
     */
    public function paginateActive(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /**
     * Ambil semua produk aktif dengan cursor pagination (efisien untuk dataset besar).
     */
    public function cursorPaginateActive(int $perPage = 100): \Illuminate\Contracts\Pagination\CursorPaginator;

    /**
     * Cari produk berdasarkan slug (tanpa filter status, untuk internal).
     */
    public function findBySlug(string $slug): ?Product;

    /**
     * Cari produk berdasarkan ID (tanpa filter status, untuk internal).
     */
    public function findById(int $id): ?Product;

    /**
     * Cari produk berdasarkan ID beserta semua relasi yang diperlukan
     * untuk membangun snapshot (categories, images, attributeValues, dll).
     */
    public function findByIdWithRelations(int $id): ?Product;

    /**
     * Ambil product IDs berdasarkan category ID.
     * Digunakan untuk mass rebuild / sync saat kategori berubah.
     *
     * @return \Illuminate\Support\Collection<int>
     */
    public function getProductIdsByCategoryId(int $categoryId): \Illuminate\Support\Collection;
}
