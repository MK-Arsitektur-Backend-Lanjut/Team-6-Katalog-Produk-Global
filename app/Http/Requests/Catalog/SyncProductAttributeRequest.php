<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class SyncProductAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Untuk endpoint sync atribut produk, biasanya tidak perlu body —
     * cukup trigger sync berdasarkan primary category.
     * Jika perlu manual override values, field opsional bisa dikirim.
     */
    public function rules(): array
    {
        return [
            // Opsional: override values secara manual
            'values' => ['nullable', 'array'],
            'values.*.attribute_id' => ['required_with:values', 'integer', 'exists:attributes,id'],
            'values.*.value' => ['required_with:values'],

            // Untuk sync category attributes
            'attributes' => ['nullable', 'array'],
            'attributes.*.attribute_id' => ['required_with:attributes', 'integer', 'exists:attributes,id'],
            'attributes.*.is_required' => ['nullable', 'boolean'],
            'attributes.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
