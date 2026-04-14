<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('id');

        return [
            'sku' => ['sometimes', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['sometimes', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999.99'],
            'rating_avg' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'status' => ['sometimes', 'string', 'in:active,inactive,draft'],

            // Kategori opsional — jika diberikan, akan di-sync
            'categories' => ['sometimes', 'array', 'min:1'],
            'categories.*.category_id' => ['required_with:categories', 'integer', 'exists:categories,id'],
            'categories.*.is_primary' => ['required_with:categories', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'SKU sudah digunakan oleh produk lain.',
            'slug.unique' => 'Slug sudah digunakan oleh produk lain.',
            'slug.regex' => 'Slug hanya boleh mengandung huruf kecil, angka, dan tanda hubung.',
        ];
    }

    /**
     * Validasi tambahan: jika categories diberikan, pastikan tepat 1 primary.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('categories')) {
                $categories = $this->input('categories', []);
                $primaryCount = collect($categories)->where('is_primary', true)->count();

                if ($primaryCount !== 1) {
                    $validator->errors()->add('categories', 'Harus ada tepat satu kategori utama (is_primary = true).');
                }
            }
        });
    }

    /**
     * Format categories ke pivot format.
     * Return null jika categories tidak diberikan.
     */
    public function categoriesPivotFormat(): ?array
    {
        if (!$this->has('categories')) {
            return null;
        }

        return collect($this->input('categories', []))
            ->mapWithKeys(fn($cat) => [
                $cat['category_id'] => ['is_primary' => $cat['is_primary']],
            ])
            ->toArray();
    }
}
