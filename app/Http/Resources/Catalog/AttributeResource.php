<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk menampilkan detail atribut (internal API).
 */
class AttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'data_type' => $this->data_type,
            'unit' => $this->unit,
            'is_required' => $this->is_required,
            'is_filterable' => $this->is_filterable,
            'validation_rules' => $this->validation_rules,
            'default_value' => $this->default_value,
            'is_active' => $this->is_active,
            'options' => $this->whenLoaded('options', function () {
                return $this->options->map(fn($opt) => [
                    'id' => $opt->id,
                    'label' => $opt->label,
                    'value' => $opt->value,
                    'sort_order' => $opt->sort_order,
                    'is_active' => $opt->is_active,
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
