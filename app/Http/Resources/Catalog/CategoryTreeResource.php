<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk menampilkan category tree secara recursive.
 * Children di-nest otomatis menggunakan CategoryTreeResource::collection().
 */
class CategoryTreeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'depth' => $this->depth,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'children' => $this->whenLoaded('childrenRecursive', function () {
                return CategoryTreeResource::collection($this->childrenRecursive);
            }, []),
        ];
    }
}
