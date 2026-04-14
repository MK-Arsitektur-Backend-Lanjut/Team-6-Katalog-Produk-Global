<?php

namespace App\Jobs\Catalog;

use App\Repositories\Contracts\Catalog\OutboxRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job untuk memproses event dari catalog_outbox table.
 *
 * Mengimplementasikan polling pattern:
 * 1. Ambil N event pending dari outbox (FIFO)
 * 2. Proses setiap event (kirim ke external service, message broker, dll)
 * 3. Tandai sebagai processed atau failed
 *
 * Saat ini (Modul 1), processing hanya menandai event sebagai processed
 * karena belum ada consumer module. Di masa depan, ini bisa mengirim
 * event ke RabbitMQ, Kafka, atau HTTP webhook.
 *
 * Bisa dijalankan sebagai:
 * - Scheduled command (setiap X menit)
 * - Queued job yang di-dispatch secara periodik
 */
class PublishCatalogOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        protected ?int $limit = null,
    ) {}

    public function handle(OutboxRepositoryInterface $outboxRepo): void
    {
        $limit = $this->limit ?? (int) config('catalog.outbox.poll_limit', 100);
        $maxRetries = (int) config('catalog.outbox.max_retries', 3);

        $pendingEvents = $outboxRepo->getPending($limit);

        if ($pendingEvents->isEmpty()) {
            Log::debug('PublishCatalogOutboxJob: No pending events.');
            return;
        }

        Log::info("PublishCatalogOutboxJob: Processing {$pendingEvents->count()} events.");

        $processed = 0;
        $failed = 0;

        foreach ($pendingEvents as $event) {
            try {
                // === FUTURE INTEGRATION POINT ===
                // Di sini bisa mengirim event ke:
                // - RabbitMQ/Kafka: $this->publishToMessageBroker($event);
                // - Webhook: $this->sendWebhook($event);
                // - Event bus: event(new CatalogEventPublished($event));
                //
                // Untuk Modul 1, cukup tandai sebagai processed.
                $this->processEvent($event);

                $outboxRepo->markProcessed($event);
                $processed++;
            } catch (\Throwable $e) {
                $outboxRepo->markFailed($event);
                $failed++;

                // Cek apakah sudah melebihi max retry
                if (!$event->canRetry($maxRetries)) {
                    Log::error("PublishCatalogOutboxJob: Event #{$event->id} exceeded max retries. Giving up.");
                } else {
                    Log::warning("PublishCatalogOutboxJob: Event #{$event->id} failed (retry {$event->retry_count}): {$e->getMessage()}");
                }

                report($e);
            }
        }

        Log::info("PublishCatalogOutboxJob: Done. Processed: {$processed}, Failed: {$failed}");
    }

    /**
     * Process individual event.
     * Override/extend ini untuk integrasi ke external system.
     */
    protected function processEvent($event): void
    {
        // Modul 1: Log event details untuk audit trail
        Log::channel('stack')->info('Catalog event published', [
            'event_id' => $event->id,
            'aggregate_type' => $event->aggregate_type,
            'aggregate_id' => $event->aggregate_id,
            'event_type' => $event->event_type,
        ]);
    }

    public function tags(): array
    {
        return ['catalog', 'outbox-publish'];
    }
}
