<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel product_view_histories menyimpan riwayat produk yang dilihat user.
     * Kombinasi user_id + product_id dibuat unique karena sistem menyimpan
     * waktu terakhir sebuah produk dilihat, bukan menyimpan event duplikat tanpa batas.
     */
    public function up(): void
    {
        Schema::create('product_view_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->unique(['user_id', 'product_id'], 'uq_product_views_user_product');
            $table->index(['user_id', 'viewed_at'], 'idx_product_views_user_viewed_at');
            $table->index('product_id', 'idx_product_views_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_view_histories');
    }
};
