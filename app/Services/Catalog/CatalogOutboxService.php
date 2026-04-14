<?php

namespace App\Services\Catalog;

use App\Repositories\Contracts\Catalog\OutboxRepositoryInterface;
use App\Models\Catalog\CatalogOutbox;

/**
 * Service untuk mencatat event perubahan katalog ke outbox table.
 *
 * Outbox pattern memastikan event tercatat dalam transaksi yang sama
 * dengan perubahan data utama, sehingga tidak ada event yang hilang.
 * Event diproses secara async oleh PublishCatalogOutboxJob.
 */
class CatalogOutboxService
{
    public function __construct(
        protected OutboxRepositoryInterface $outboxRepo,
    ) {}

    // =========================================================================
    // EVENT TYPES (constants)
    // =========================================================================

    public const EVENT_PRODUCT_CREATED = 'product.created';
    public const EVENT_PRODUCT_UPDATED = 'product.updated';
    public const EVENT_PRODUCT_DELETED = 'product.deleted';
    public const EVENT_PRODUCT_SNAPSHOT_REBUILT = 'product.snapshot_rebuilt';
    public const EVENT_CATEGORY_CREATED = 'category.created';
    public const EVENT_CATEGORY_UPDATED = 'category.updated';
    public const EVENT_CATEGORY_DELETED = 'category.deleted';
    public const EVENT_ATTRIBUTE_SYNCED = 'product.attributes_synced';

    // =========================================================================
    // AGGREGATE TYPES
    // =========================================================================

    public const AGGREGATE_PRODUCT = 'product';
    public const AGGREGATE_CATEGORY = 'category';

    // =========================================================================
    // RECORD METHODS
    // =========================================================================

    /**
     * Catat event produk dibuat.
     */
    public function recordProductCreated(int $productId, array $data = []): CatalogOutbox
    {
        return $this->record(
            self::AGGREGATE_PRODUCT,
            $productId,
            self::EVENT_PRODUCT_CREATED,
            $data
        );
    }

    /**
     * Catat event produk diupdate.
     */
    public function recordProductUpdated(int $productId, array $changedFields = []): CatalogOutbox
    {
        return $this->record(
            self::AGGREGATE_PRODUCT,
            $productId,
            self::EVENT_PRODUCT_UPDATED,
            ['changed_fields' => $changedFields]
        );
    }

    /**
     * Catat event produk dihapus (soft delete).
     */
    public function recordProductDeleted(int $productId): CatalogOutbox
    {
        return $this->record(
            self::AGGREGATE_PRODUCT,
            $productId,
            self::EVENT_PRODUCT_DELETED,
            []
        );
    }

    /**
     * Catat event snapshot produk di-rebuild.
     */
    public function recordSnapshotRebuilt(int $productId, int $version): CatalogOutbox
    {
        return $this->record(
            self::AGGREGATE_PRODUCT,
            $productId,
            self::EVENT_PRODUCT_SNAPSHOT_REBUILT,
            ['metadata_version' => $version]
        );
    }

    /**
     * Catat event kategori dibuat.
     */
    public function recordCategoryCreated(int $categoryId, array $data = []): CatalogOutbox
    {
        return $this->record(
            self::AGGREGATE_CATEGORY,
            $categoryId,
            self::EVENT_CATEGORY_CREATED,
            $data
        );
    }

    /**
     * Catat event kategori diupdate.
     */
    public function recordCategoryUpdated(int $categoryId, array $changedFields = []): CatalogOutbox
    {
        return $this->record(
            self::AGGREGATE_CATEGORY,
            $categoryId,
            self::EVENT_CATEGORY_UPDATED,
            ['changed_fields' => $changedFields]
        );
    }

    /**
     * Catat event kategori dihapus.
     */
    public function recordCategoryDeleted(int $categoryId): CatalogOutbox
    {
        return $this->record(
            self::AGGREGATE_CATEGORY,
            $categoryId,
            self::EVENT_CATEGORY_DELETED,
            []
        );
    }

    /**
     * Catat event atribut produk disinkronkan.
     */
    public function recordAttributesSynced(int $productId, array $attributeIds = []): CatalogOutbox
    {
        return $this->record(
            self::AGGREGATE_PRODUCT,
            $productId,
            self::EVENT_ATTRIBUTE_SYNCED,
            ['synced_attribute_ids' => $attributeIds]
        );
    }

    // =========================================================================
    // INTERNAL
    // =========================================================================

    /**
     * Method internal untuk mencatat event ke outbox.
     */
    protected function record(
        string $aggregateType,
        int $aggregateId,
        string $eventType,
        array $payload
    ): CatalogOutbox {
        return $this->outboxRepo->create([
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }
}
