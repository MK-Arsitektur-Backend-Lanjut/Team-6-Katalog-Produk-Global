<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attributeId = $this->route('id');

        return [
            'code' => ['sometimes', 'string', 'max:100', Rule::unique('attributes', 'code')->ignore($attributeId), 'regex:/^[a-z0-9_]+$/'],
            'name' => ['sometimes', 'string', 'max:255'],
            'data_type' => ['sometimes', 'string', 'in:text,integer,decimal,boolean,date,select,multiselect,json'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
            'is_filterable' => ['nullable', 'boolean'],
            'validation_rules' => ['nullable', 'array'],
            'default_value' => ['nullable'],
            'is_active' => ['nullable', 'boolean'],

            // Opsi (opsional saat update)
            'options' => ['sometimes', 'array'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'options.*.value' => ['required_with:options', 'string', 'max:255'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode atribut sudah digunakan.',
            'code.regex' => 'Kode atribut hanya boleh mengandung huruf kecil, angka, dan underscore.',
        ];
    }
}
