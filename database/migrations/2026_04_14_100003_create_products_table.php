<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel products menyimpan data utama produk katalog.
     * Kolom metadata_snapshot (JSON) menyimpan denormalized snapshot yang siap baca
     * untuk menghindari JOIN besar pada read path.
     * Kolom metadata_version di-increment setiap kali ada perubahan metadata
     * untuk tracking konsistensi snapshot.
     * Status: 'active' (public), 'inactive' (hidden), 'draft' (WIP).
     * Soft delete agar data historis tidak hilang.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('metadata_version')->default(1);
            $table->json('metadata_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_products_status');
            $table->index('price', 'idx_products_price');
            $table->index('rating_avg', 'idx_products_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
