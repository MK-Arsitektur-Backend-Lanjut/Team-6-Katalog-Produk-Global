<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel product_images menyimpan URL gambar produk.
     * Kolom position mengatur urutan tampilan gambar.
     * Kolom is_primary menandai gambar utama (hanya satu per produk).
     * Cascade delete saat produk dihapus.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('alt_text')->nullable();
            $table->tinyInteger('position')->unsigned()->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('product_id', 'idx_product_images_product_id');
            $table->index(['product_id', 'is_primary'], 'idx_product_images_is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
