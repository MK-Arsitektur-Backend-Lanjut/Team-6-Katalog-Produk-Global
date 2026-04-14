<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CatalogOutbox extends Model
{
    protected $table = 'catalog_outbox';

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'status',
        'retry_count',
        'available_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'aggregate_id' => 'integer',
            'payload' => 'array',
            'retry_count' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // CONSTANTS — Status lifecycle
    // =========================================================================

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: event yang menunggu diproses dan sudah melewati available_at.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->where('available_at', '<=', now());
    }

    /**
     * Scope: event yang gagal diproses.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope: event yang sudah diproses.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    /**
     * Scope: filter berdasarkan aggregate.
     */
    public function scopeForAggregate(Builder $query, string $type, int $id): Builder
    {
        return $query->where('aggregate_type', $type)
                     ->where('aggregate_id', $id);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Tandai event sebagai processed.
     */
    public function markAsProcessed(): bool
    {
        return $this->update([
            'status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Tandai event sebagai failed dan increment retry_count.
     */
    public function markAsFailed(): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Cek apakah event masih bisa di-retry (max 3 kali).
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->retry_count < $maxRetries;
    }
}
