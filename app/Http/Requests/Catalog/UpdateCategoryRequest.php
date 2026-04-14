<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('id');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($categoryId), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validasi tambahan: parent_id tidak boleh dirinya sendiri.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $categoryId = (int) $this->route('id');

            if ($this->has('parent_id') && (int) $this->input('parent_id') === $categoryId) {
                $validator->errors()->add('parent_id', 'Kategori tidak bisa menjadi parent dari dirinya sendiri.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Slug kategori sudah digunakan.',
            'slug.regex' => 'Slug hanya boleh mengandung huruf kecil, angka, dan tanda hubung.',
            'parent_id.exists' => 'Kategori parent tidak ditemukan.',
        ];
    }
}
