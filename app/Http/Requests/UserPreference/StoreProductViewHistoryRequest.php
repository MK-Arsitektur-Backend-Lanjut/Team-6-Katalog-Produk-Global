<?php

namespace App\Http\Requests\UserPreference;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductViewHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1', 'exists:products,id'],
        ];
    }
}
