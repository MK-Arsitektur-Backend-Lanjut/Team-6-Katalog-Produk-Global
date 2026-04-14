<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
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
