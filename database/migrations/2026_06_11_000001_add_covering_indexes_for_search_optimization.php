<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covering index untuk query bulk-search dan suggest.
     *
     * ── MASALAH YANG DIPECAHKAN ──────────────────────────────────────────────
     *
     * Query bulk-search:
     *   SELECT id, sku, slug, name, ... FROM products
     *   WHERE status = 'active' AND id IN (1, 2, 3, ...)
     *
     * Tanpa index yang tepat, MySQL harus:
     * 1. Gunakan PRIMARY KEY untuk mencari setiap ID (range scan)
     * 2. Kemudian filter baris yang status = 'active' (extra filter pass)
     *
     * ── SOLUSI ───────────────────────────────────────────────────────────────
     *
     * Composite index (status, id) — disebut "covering" karena mencakup
     * kedua kolom yang dipakai di WHERE clause sekaligus:
     *   WHERE status = 'active' AND id IN (...)
     *
     * Dengan index ini, MySQL dapat:
     * 1. Langsung lookup di index untuk status = 'active'
     * 2. Kemudian hanya scan baris dengan ID yang diminta
     * → Menghindari extra filter pass di baris yang tidak relevan
     *
     * ── INDEX UNTUK SUGGEST ──────────────────────────────────────────────────
     *
     * Query suggest:
     *   SELECT ... FROM product_categories
     *   WHERE product_id = ? AND is_primary = 1
     *
     * Index (product_id, is_primary) memungkinkan lookup langsung
     * tanpa full-scan pada tabel product_categories.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Covering index untuk bulk-search: WHERE status = 'active' AND id IN (...)
            // Urutan (status, id): status di depan karena selectivitas rendah tapi
            // selalu ada di WHERE, id di belakang karena high-cardinality.
            $table->index(['status', 'id'], 'idx_products_status_id');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            // Index untuk suggest: WHERE product_id = ? AND is_primary = 1
            // Membantu query primary category lookup yang dipakai suggest & stats.
            $table->index(['product_id', 'is_primary'], 'idx_pc_product_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropIndex('idx_pc_product_primary');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_status_id');
        });
    }
};
