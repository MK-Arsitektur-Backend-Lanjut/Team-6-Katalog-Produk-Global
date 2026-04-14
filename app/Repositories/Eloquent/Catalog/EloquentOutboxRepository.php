<?php

namespace App\Repositories\Eloquent\Catalog;

use App\Models\Catalog\CatalogOutbox;
use App\Repositories\Contracts\Catalog\OutboxRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentOutboxRepository implements OutboxRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $data): CatalogOutbox
    {
        return CatalogOutbox::create(array_merge([
            'status' => CatalogOutbox::STATUS_PENDING,
            'retry_count' => 0,
            'available_at' => now(),
        ], $data));
    }

    /**
     * {@inheritdoc}
     */
    public function markProcessed(CatalogOutbox $outbox): bool
    {
        return $outbox->markAsProcessed();
    }

    /**
     * {@inheritdoc}
     */
    public function markFailed(CatalogOutbox $outbox): bool
    {
        return $outbox->markAsFailed();
    }

    /**
     * {@inheritdoc}
     *
     * Mengambil event pending yang sudah melewati available_at.
     * Terurut berdasarkan created_at (FIFO).
     */
    public function getPending(int $limit = 100): Collection
    {
        return CatalogOutbox::pending()
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByAggregate(string $aggregateType, int $aggregateId): Collection
    {
        return CatalogOutbox::forAggregate($aggregateType, $aggregateId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
