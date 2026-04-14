<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel product_categories mengelola relasi many-to-many antara produk dan kategori.
     * Composite primary key (product_id, category_id) mencegah duplikasi.
     * Kolom is_primary menandai kategori utama (hanya satu per produk).
     * Kategori utama digunakan untuk:
     *   - Breadcrumbs di halaman detail
     *   - Penentuan inherited attributes
     *   - Primary classification
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->primary(['product_id', 'category_id']);
            $table->index('category_id', 'idx_product_categories_category');
            $table->index(['product_id', 'is_primary'], 'idx_product_categories_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
