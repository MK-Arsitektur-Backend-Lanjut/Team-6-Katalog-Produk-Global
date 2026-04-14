<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'unique:attributes,code', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'data_type' => ['required', 'string', 'in:text,integer,decimal,boolean,date,select,multiselect,json'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
            'is_filterable' => ['nullable', 'boolean'],
            'validation_rules' => ['nullable', 'array'],
            'default_value' => ['nullable'],
            'is_active' => ['nullable', 'boolean'],

            // Opsi untuk tipe select/multiselect
            'options' => ['required_if:data_type,select,multiselect', 'array'],
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
            'options.required_if' => 'Opsi wajib diisi untuk tipe select/multiselect.',
        ];
    }
}
