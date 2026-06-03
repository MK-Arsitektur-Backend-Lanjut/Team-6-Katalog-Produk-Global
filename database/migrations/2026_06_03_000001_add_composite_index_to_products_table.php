<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite index untuk optimasi query listing produk aktif.
     *
     * Query target: WHERE status = 'active' AND deleted_at IS NULL ORDER BY created_at DESC
     * Tanpa index ini, MySQL harus melakukan filesort pada 10.000+ row.
     * Dengan index ini, MySQL bisa langsung scan index secara berurutan.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(
                ['status', 'deleted_at', 'created_at'],
                'idx_products_active_listing'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_active_listing');
        });
    }
};
