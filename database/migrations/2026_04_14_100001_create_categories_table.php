<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel categories menyimpan kategori produk bertingkat (hierarkis).
     * Menggunakan self-referencing foreign key (parent_id) untuk relasi parent-child.
     * Kolom path menyimpan materialized path untuk display (misal: "elektronik/smartphone/android").
     * Kolom depth menyimpan level kedalaman (0 = root).
     * Soft delete digunakan agar kategori yang masih direferensikan tidak hilang.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('categories')
                  ->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('path', 1000)->default('');
            $table->tinyInteger('depth')->unsigned()->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id', 'idx_categories_parent_id');
            $table->index('is_active', 'idx_categories_is_active');
            $table->index('depth', 'idx_categories_depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
