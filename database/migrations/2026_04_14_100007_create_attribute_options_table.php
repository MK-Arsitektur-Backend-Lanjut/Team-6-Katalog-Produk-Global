<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel attribute_options menyimpan opsi yang tersedia untuk atribut bertipe 'select' atau 'multiselect'.
     * Contoh: attribute "color" memiliki options ["Red", "Blue", "Green"].
     * Unique constraint (attribute_id, value) mencegah duplikasi value pada satu atribut.
     * sort_order mengatur urutan tampilan opsi.
     */
    public function up(): void
    {
        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')
                  ->constrained('attributes')
                  ->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('attribute_id', 'idx_attr_options_attribute');
            $table->unique(['attribute_id', 'value'], 'uq_attr_options_attr_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_options');
    }
};
