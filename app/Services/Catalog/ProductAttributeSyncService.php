<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Attribute;
use App\Models\Catalog\Product;
use App\Repositories\Contracts\Catalog\AttributeRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductAttributeValueRepositoryInterface;
use App\Repositories\Contracts\Catalog\ProductReadRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk sinkronisasi atribut produk berdasarkan kategori.
 *
 * Responsibility:
 * - Resolve inherited attributes dari seluruh ancestor chain
 * - Sync nilai atribut produk: tambah yang baru, hapus yang tidak lagi relevan
 * - Menerapkan default values untuk atribut baru
 * - Trigger rebuild snapshot setelah sync
 *
 * Flow sinkronisasi:
 * 1. Ambil primary category produk
 * 2. Resolve semua inherited attributes (root → leaf)
 * 3. Bandingkan dengan atribut yang sudah ada di produk
 * 4. Tambah atribut baru (dengan default value jika ada)
 * 5. Hapus atribut yang tidak lagi relevan
 * 6. Rebuild snapshot
 */
class ProductAttributeSyncService
{
    public function __construct(
        protected AttributeRepositoryInterface $attributeRepo,
        protected ProductAttributeValueRepositoryInterface $attributeValueRepo,
        protected ProductReadRepositoryInterface $productReadRepo,
        protected ProductSnapshotService $snapshotService,
        protected CatalogCacheService $cacheService,
        protected CatalogOutboxService $outboxService,
    ) {}

    /**
     * Sync atribut untuk satu produk berdasarkan primary category-nya.
     *
     * @param int $productId
     * @return array Informasi sync: added, removed, retained counts
     */
    public function syncProductAttributes(int $productId): array
    {
        $product = $this->productReadRepo->findByIdWithRelations($productId);

        if (!$product) {
            throw new \InvalidArgumentException("Product #{$productId} not found.");
        }

        // Ambil primary category
        $primaryCategory = $product->categories
            ->firstWhere('pivot.is_primary', true);

        if (!$primaryCategory) {
            return ['added' => 0, 'removed' => 0, 'retained' => 0];
        }

        return $this->syncWithCategory($product, $primaryCategory->id);
    }

    /**
     * Sync atribut produk dengan kategori tertentu.
     *
     * @return array Informasi sync: added, removed, retained counts
     */
    public function syncWithCategory(Product $product, int $categoryId): array
    {
        return DB::transaction(function () use ($product, $categoryId) {
            // 1. Resolve inherited attributes dari ancestor chain
            $inheritedAttributes = $this->attributeRepo->getInheritedAttributes($categoryId);
            $inheritedAttributeIds = $inheritedAttributes->pluck('id')->toArray();

            // 2. Ambil atribut yang sudah ada di produk
            $currentValues = $this->attributeValueRepo->getByProduct($product->id);
            $currentAttributeIds = $currentValues->pluck('attribute_id')->toArray();

            // 3. Tentukan yang perlu ditambah dan dihapus
            $toAdd = array_diff($inheritedAttributeIds, $currentAttributeIds);
            $toRemove = array_diff($currentAttributeIds, $inheritedAttributeIds);
            $retained = array_intersect($currentAttributeIds, $inheritedAttributeIds);

            // 4. Tambah atribut baru dengan default value
            foreach ($toAdd as $attributeId) {
                $attribute = $inheritedAttributes->firstWhere('id', $attributeId);
                $defaultData = $this->buildDefaultValueData($attribute);

                $this->attributeValueRepo->upsertValue(
                    $product->id,
                    $attributeId,
                    $defaultData
                );
            }

            // 5. Hapus atribut yang tidak lagi relevan
            if (!empty($toRemove)) {
                $this->attributeValueRepo->deleteByProductAndAttributes(
                    $product->id,
                    $toRemove
                );
            }

            // 6. Rebuild snapshot
            $this->snapshotService->rebuildSnapshot($product->id);

            // 7. Invalidate cache
            $this->cacheService->invalidateProduct($product->id, $product->slug);

            // 8. Record outbox
            $this->outboxService->recordAttributesSynced(
                $product->id,
                $inheritedAttributeIds
            );

            return [
                'added' => count($toAdd),
                'removed' => count($toRemove),
                'retained' => count($retained),
            ];
        });
    }

    /**
     * Sync atribut untuk semua produk dalam satu kategori.
     * Digunakan saat definisi category_attributes berubah.
     *
     * @param int $categoryId
     * @return array<int> Product IDs yang berhasil di-sync
     */
    public function syncCategoryProducts(int $categoryId): array
    {
        $productIds = $this->productReadRepo
            ->getProductIdsByCategoryId($categoryId)
            ->toArray();

        $syncedIds = [];

        foreach ($productIds as $productId) {
            try {
                $this->syncProductAttributes($productId);
                $syncedIds[] = $productId;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $syncedIds;
    }

    /**
     * Resolve semua inherited attributes untuk kategori tertentu.
     * Mengembalikan Collection of Attribute models terurut root → leaf.
     */
    public function resolveInheritedAttributes(int $categoryId): Collection
    {
        return $this->attributeRepo->getInheritedAttributes($categoryId);
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Build default value data untuk atribut baru.
     * Mengisi kolom value yang sesuai berdasarkan data_type dan default_value atribut.
     */
    protected function buildDefaultValueData(Attribute $attribute): array
    {
        $data = [
            'display_value' => null,
        ];

        // Jika ada default_value, set ke kolom yang sesuai
        if ($attribute->default_value !== null) {
            $defaultVal = is_array($attribute->default_value)
                ? ($attribute->default_value['value'] ?? null)
                : $attribute->default_value;

            if ($defaultVal !== null) {
                $valueColumn = $attribute->getValueColumn();
                $data[$valueColumn] = $defaultVal;
                $data['display_value'] = $this->formatDisplayValue($attribute, $defaultVal);
            }
        }

        return $data;
    }

    /**
     * Format display value berdasarkan data_type dan unit.
     */
    protected function formatDisplayValue(Attribute $attribute, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $display = match ($attribute->data_type) {
            'boolean' => $value ? 'Yes' : 'No',
            'select' => $this->resolveOptionLabel($attribute, $value),
            'multiselect' => is_array($value) ? implode(', ', $value) : (string) $value,
            default => (string) $value,
        };

        // Tambahkan unit jika ada
        if ($attribute->unit && !in_array($attribute->data_type, ['boolean', 'select', 'multiselect'])) {
            $display .= ' ' . $attribute->unit;
        }

        return $display;
    }

    /**
     * Resolve label dari attribute option berdasarkan option ID atau value.
     */
    protected function resolveOptionLabel(Attribute $attribute, mixed $value): string
    {
        if (!$attribute->relationLoaded('options')) {
            $attribute->load('options');
        }

        // Coba match by ID dulu, lalu by value
        $option = $attribute->options->firstWhere('id', $value)
            ?? $attribute->options->firstWhere('value', $value);

        return $option ? $option->label : (string) $value;
    }
}
