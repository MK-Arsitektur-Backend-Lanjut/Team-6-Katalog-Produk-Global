<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk detail produk (public read API).
 *
 * Mengambil data dari metadata_snapshot yang sudah denormalized
 * sehingga response sudah siap tanpa query tambahan.
 */
class ProductDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * $this->resource berisi array dari ProductDetailService::formatResponse()
     */
    public function toArray(Request $request): array
    {
        // Jika resource adalah array (dari service), return as-is
        if (is_array($this->resource)) {
            return $this->resource;
        }

        // Fallback: jika resource adalah Product model (direct usage)
        $snapshot = $this->resource->metadata_snapshot ?? [];

        return [
            'id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'short_description' => $this->resource->short_description,
            'description' => $this->resource->description,
            'price' => (float) $this->resource->price,
            'rating_avg' => (float) $this->resource->rating_avg,
            'primary_category' => $snapshot['primary_category'] ?? null,
            'breadcrumbs' => $snapshot['breadcrumbs'] ?? [],
            'categories' => $snapshot['categories'] ?? [],
            'attributes' => $snapshot['attributes'] ?? [],
            'images' => $snapshot['images'] ?? [],
            'metadata_version' => $this->resource->metadata_version,
        ];
    }
}
