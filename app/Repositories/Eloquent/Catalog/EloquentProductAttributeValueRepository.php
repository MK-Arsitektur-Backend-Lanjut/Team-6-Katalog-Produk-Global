<?php

namespace App\Repositories\Eloquent\Catalog;

use App\Models\Catalog\ProductAttributeValue;
use App\Repositories\Contracts\Catalog\ProductAttributeValueRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentProductAttributeValueRepository implements ProductAttributeValueRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getByProduct(int $productId): Collection
    {
        return ProductAttributeValue::where('product_id', $productId)
            ->with(['attribute', 'option'])
            ->get();
    }

    /**
     * {@inheritdoc}
     *
     * Menggunakan updateOrCreate untuk atomic upsert.
     * Key: (product_id, attribute_id) — sesuai unique constraint di DB.
     */
    public function upsertValue(int $productId, int $attributeId, array $data): ProductAttributeValue
    {
        return ProductAttributeValue::updateOrCreate(
            [
                'product_id' => $productId,
                'attribute_id' => $attributeId,
            ],
            array_merge($data, [
                'synced_at' => now(),
            ])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByProduct(int $productId): int
    {
        return ProductAttributeValue::where('product_id', $productId)->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByProductAndAttributes(int $productId, array $attributeIds): int
    {
        return ProductAttributeValue::where('product_id', $productId)
            ->whereIn('attribute_id', $attributeIds)
            ->delete();
    }

    /**
     * {@inheritdoc}
     *
     * Sync strategy:
     * 1. Ambil attribute_ids yang ada saat ini
     * 2. Tentukan mana yang perlu di-delete (ada di DB tapi tidak di $values)
     * 3. Upsert sisanya
     */
    public function syncValues(int $productId, array $values): void
    {
        $incomingAttributeIds = collect($values)->pluck('attribute_id')->toArray();

        // Hapus atribut yang tidak ada di incoming values
        $currentAttributeIds = ProductAttributeValue::where('product_id', $productId)
            ->pluck('attribute_id')
            ->toArray();

        $toDelete = array_diff($currentAttributeIds, $incomingAttributeIds);
        if (!empty($toDelete)) {
            $this->deleteByProductAndAttributes($productId, $toDelete);
        }

        // Upsert incoming values
        foreach ($values as $value) {
            $attributeId = $value['attribute_id'];
            unset($value['attribute_id']);

            $this->upsertValue($productId, $attributeId, $value);
        }
    }
}
