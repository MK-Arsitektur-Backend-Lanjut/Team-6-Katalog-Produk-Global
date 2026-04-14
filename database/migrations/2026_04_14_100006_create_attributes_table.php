<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel attributes menyimpan definisi atribut produk.
     * data_type menentukan kolom mana yang dipakai di product_attribute_values:
     *   - 'text'        → value_text
     *   - 'integer'     → value_integer
     *   - 'decimal'     → value_decimal
     *   - 'boolean'     → value_boolean
     *   - 'date'        → value_date
     *   - 'select'      → attribute_option_id
     *   - 'multiselect' → value_json
     *   - 'json'        → value_json
     * validation_rules (JSON) menyimpan aturan validasi kustom (min, max, regex, dll).
     * default_value (JSON) menyimpan nilai default yang bisa diterapkan saat sync.
     */
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('data_type', 20)->default('text');
            $table->string('unit', 50)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('default_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('data_type', 'idx_attributes_data_type');
            $table->index('is_filterable', 'idx_attributes_is_filterable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
