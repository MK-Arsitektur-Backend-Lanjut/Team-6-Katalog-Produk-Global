<?php

namespace App\Http\Resources\UserPreference;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductViewHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'sku' => $this->product->sku,
                    'slug' => $this->product->slug,
                    'name' => $this->product->name,
                    'price' => $this->product->price,
                    'rating_avg' => $this->product->rating_avg,
                    'status' => $this->product->status,
                ];
            }),
            'viewed_at' => $this->viewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
