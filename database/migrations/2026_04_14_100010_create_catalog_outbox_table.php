<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel catalog_outbox mengimplementasikan Transactional Outbox pattern.
     * Setiap perubahan metadata katalog (produk, kategori, atribut) dicatat sebagai event.
     * Event ini bisa dikonsumsi oleh modul lain (future integration) melalui polling atau job.
     *
     * Flow: Write operation → insert outbox event (dalam transaksi yang sama) → job memproses event.
     *
     * Status lifecycle: pending → processed / failed
     * retry_count digunakan untuk dead letter logic (misal: max 3 retry).
     * available_at mendukung delayed/scheduled event processing.
     */
    public function up(): void
    {
        Schema::create('catalog_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 100);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type', 100);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->tinyInteger('retry_count')->unsigned()->default(0);
            $table->timestamp('available_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at'], 'idx_outbox_status_available');
            $table->index(['aggregate_type', 'aggregate_id'], 'idx_outbox_aggregate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_outbox');
    }
};
