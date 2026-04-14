<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel category_attributes menghubungkan atribut ke kategori.
     * Atribut yang didefinisikan pada kategori ancestor akan diwariskan ke kategori descendant.
     * Contoh:
     *   - Elektronik (depth 0): brand, warranty
     *   - Smartphone (depth 1): battery, screen_size
     *   - Android (depth 2): ram, storage
     *   → Produk di kategori Android mewarisi: brand, warranty, battery, screen_size, ram, storage
     * is_required di level ini bisa override is_required default dari tabel attributes.
     * sort_order mengatur urutan tampilan atribut dalam konteks kategori.
     */
    public function up(): void
    {
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->cascadeOnDelete();
            $table->foreignId('attribute_id')
                  ->constrained('attributes')
                  ->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['category_id', 'attribute_id']);
            $table->index('attribute_id', 'idx_category_attributes_attribute');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_attributes');
    }
};
