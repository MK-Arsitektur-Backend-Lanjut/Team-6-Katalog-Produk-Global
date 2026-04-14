<?php

namespace App\Repositories\Contracts\Catalog;

use App\Models\Catalog\CatalogOutbox;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface untuk operasi repository outbox event.
 * Outbox pattern memastikan event tercatat dalam transaksi yang sama
 * dengan perubahan data, lalu diproses async oleh job.
 */
interface OutboxRepositoryInterface
{
    /**
     * Buat outbox event baru.
     */
    public function create(array $data): CatalogOutbox;

    /**
     * Tandai event sebagai processed.
     */
    public function markProcessed(CatalogOutbox $outbox): bool;

    /**
     * Tandai event sebagai failed.
     */
    public function markFailed(CatalogOutbox $outbox): bool;

    /**
     * Ambil event yang pending (siap diproses).
     */
    public function getPending(int $limit = 100): Collection;

    /**
     * Ambil event berdasarkan aggregate type dan ID.
     */
    public function getByAggregate(string $aggregateType, int $aggregateId): Collection;
}
