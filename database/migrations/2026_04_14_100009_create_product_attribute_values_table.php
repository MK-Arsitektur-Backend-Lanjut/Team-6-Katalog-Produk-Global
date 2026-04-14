<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel product_attribute_values menyimpan nilai atribut per produk.
     * Menggunakan EAV (Entity-Attribute-Value) pattern dengan multi-type columns:
     *   - value_text     → untuk data_type 'text'
     *   - value_integer  → untuk data_type 'integer'
     *   - value_decimal  → untuk data_type 'decimal'
     *   - value_boolean  → untuk data_type 'boolean'
     *   - value_date     → untuk data_type 'date'
     *   - value_json     → untuk data_type 'multiselect' atau 'json'
     *   - attribute_option_id → untuk data_type 'select' (FK ke attribute_options)
     *
     * display_value menyimpan representasi string yang siap tampil (pre-computed).
     * synced_at menandai kapan terakhir kali value ini disinkronkan.
     * Unique constraint (product_id, attribute_id) memastikan satu produk hanya punya satu nilai per atribut.
     */
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->foreignId('attribute_id')
                  ->constrained('attributes')
                  ->cascadeOnDelete();
            $table->foreignId('attribute_option_id')
                  ->nullable()
                  ->constrained('attribute_options')
                  ->nullOnDelete();
            $table->text('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 15, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->string('display_value', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id'], 'uq_product_attribute');
            $table->index('attribute_id', 'idx_pav_attribute');
            $table->index('attribute_option_id', 'idx_pav_option');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
