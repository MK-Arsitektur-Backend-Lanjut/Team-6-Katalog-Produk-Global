<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite index untuk optimasi query listing produk aktif.
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
