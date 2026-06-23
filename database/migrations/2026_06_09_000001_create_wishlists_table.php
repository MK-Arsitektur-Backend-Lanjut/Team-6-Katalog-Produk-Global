<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel wishlists menyimpan daftar produk yang disimpan user.
     * Kombinasi user_id + product_id dibuat unique supaya satu user
     * tidak dapat menyimpan produk yang sama lebih dari satu kali.
     */
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'product_id'], 'uq_wishlists_user_product');
            $table->index('user_id', 'idx_wishlists_user');
            $table->index('product_id', 'idx_wishlists_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
