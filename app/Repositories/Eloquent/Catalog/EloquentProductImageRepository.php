<?php

namespace App\Repositories\Eloquent\Catalog;

use App\Models\Catalog\ProductImage;
use App\Repositories\Contracts\Catalog\ProductImageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentProductImageRepository implements ProductImageRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getByProduct(int $productId): Collection
    {
        return ProductImage::where('product_id', $productId)
            ->orderBy('position')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): ProductImage
    {
        return ProductImage::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(ProductImage $image, array $data): ProductImage
    {
        $image->update($data);
        return $image->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(ProductImage $image): bool
    {
        return $image->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByProduct(int $productId): int
    {
        return ProductImage::where('product_id', $productId)->delete();
    }

    /**
     * {@inheritdoc}
     *
     * Reorder: set position berdasarkan index dalam $orderedIds.
     * Contoh: orderedIds = [5, 3, 7] → image 5 position=0, image 3 position=1, image 7 position=2
     */
    public function reorder(int $productId, array $orderedIds): void
    {
        foreach ($orderedIds as $position => $imageId) {
            ProductImage::where('id', $imageId)
                ->where('product_id', $productId)
                ->update(['position' => $position]);
        }
    }
}
