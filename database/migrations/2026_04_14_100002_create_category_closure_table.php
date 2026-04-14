<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel category_closure mengimplementasikan Closure Table pattern untuk hierarki kategori.
     * Setiap baris merepresentasikan relasi ancestor-descendant (termasuk self-reference depth=0).
     * Pattern ini memungkinkan query O(1) untuk:
     *   - Mendapatkan semua ancestor dari sebuah kategori
     *   - Mendapatkan semua descendant dari sebuah kategori
     *   - Membangun breadcrumbs
     *   - Membangun subtree
     * Composite primary key (ancestor_id, descendant_id) mencegah duplikasi.
     */
    public function up(): void
    {
        Schema::create('category_closure', function (Blueprint $table) {
            $table->foreignId('ancestor_id')
                  ->constrained('categories')
                  ->cascadeOnDelete();
            $table->foreignId('descendant_id')
                  ->constrained('categories')
                  ->cascadeOnDelete();
            $table->tinyInteger('depth')->unsigned()->default(0);

            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index('descendant_id', 'idx_closure_descendant');
            $table->index('depth', 'idx_closure_depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_closure');
    }
};
