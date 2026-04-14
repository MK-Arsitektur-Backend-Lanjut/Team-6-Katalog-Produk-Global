<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'rating_avg' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'status' => ['nullable', 'string', 'in:active,inactive,draft'],

            // Kategori: array of objects dengan category_id dan is_primary
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'categories.*.is_primary' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'SKU sudah digunakan oleh produk lain.',
            'slug.unique' => 'Slug sudah digunakan oleh produk lain.',
            'slug.regex' => 'Slug hanya boleh mengandung huruf kecil, angka, dan tanda hubung.',
            'categories.required' => 'Minimal satu kategori harus dipilih.',
            'categories.*.category_id.exists' => 'Kategori tidak ditemukan.',
        ];
    }

    /**
     * Validasi tambahan: pastikan tepat satu kategori primary.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $categories = $this->input('categories', []);
            $primaryCount = collect($categories)->where('is_primary', true)->count();

            if ($primaryCount !== 1) {
                $validator->errors()->add('categories', 'Harus ada tepat satu kategori utama (is_primary = true).');
            }
        });
    }

    /**
     * Format categories ke pivot format: [category_id => ['is_primary' => bool]]
     */
    public function categoriesPivotFormat(): array
    {
        return collect($this->input('categories', []))
            ->mapWithKeys(fn($cat) => [
                $cat['category_id'] => ['is_primary' => $cat['is_primary']],
            ])
            ->toArray();
    }
}
