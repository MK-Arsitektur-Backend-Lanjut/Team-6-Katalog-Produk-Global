<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan composite index pada tabel products untuk mengoptimalkan
     * query autocomplete: WHERE status = 'active' AND deleted_at IS NULL AND name LIKE 'prefix%' ORDER BY name ASC
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(
                ['status', 'deleted_at', 'name'],
                'idx_products_autocomplete'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_autocomplete');
        });
    }
};
