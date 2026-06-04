<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for product search, facets, autocomplete, and suggestions.
     *
     * These composite indexes target the most common predicates in the public
     * catalog search endpoints: active products filtered/sorted by price,
     * rating, creation time, name prefix, and category membership.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'price'], 'idx_products_status_price');
            $table->index(['status', 'rating_avg'], 'idx_products_status_rating_avg');
            $table->index(['status', 'created_at'], 'idx_products_status_created_at');
            $table->index(['status', 'name'], 'idx_products_status_name');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('products', function (Blueprint $table) {
                $table->fullText(['name', 'short_description'], 'ft_products_search');
            });
        }

        Schema::table('product_categories', function (Blueprint $table) {
            $table->index(['category_id', 'product_id'], 'idx_pc_category_product');
            $table->index(['category_id', 'is_primary', 'product_id'], 'idx_pc_category_primary_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropIndex('idx_pc_category_primary_product');
            $table->dropIndex('idx_pc_category_product');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropFullText('ft_products_search');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_status_name');
            $table->dropIndex('idx_products_status_created_at');
            $table->dropIndex('idx_products_status_rating_avg');
            $table->dropIndex('idx_products_status_price');
        });
    }
};
