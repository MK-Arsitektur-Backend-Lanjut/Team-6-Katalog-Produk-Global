<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request untuk validasi parameter search produk.
 *
 * Kenapa dibutuhkan:
 * - Mencegah input berbahaya (keyword terlalu panjang bisa menyebabkan LIKE query lambat)
 * - Memberikan pesan error 422 yang jelas kepada consumer API
 * - Mencegah kombinasi filter yang logis salah (min_price > max_price)
 * - Memastikan pagination parameter dalam batas aman
 */
class ProductSearchRequest extends FormRequest
{
    /**
     * Semua request search adalah public (tidak butuh auth).
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Keyword dibatasi 100 karakter untuk mencegah LIKE query ekstrem yang lambat.
            // String LIKE '%...sangat panjang...%' akan memaksa full-table scan yang mahal.
            'keyword'     => ['sometimes', 'string', 'max:100'],

            // category_id harus integer positif
            'category_id' => ['sometimes', 'integer', 'min:1'],

            // min_price harus numerik non-negatif
            'min_price'   => ['sometimes', 'numeric', 'min:0'],

            // max_price harus >= min_price (cross-field validation).
            // Tanpa ini, filter dengan min > max akan selalu menghasilkan 0 hasil
            // tanpa pesan error yang informatif.
            'max_price'   => ['sometimes', 'numeric', 'min:0', 'gte:min_price'],

            // rating antara 0-5
            'min_rating'  => ['sometimes', 'numeric', 'min:0', 'max:5'],

            // sort hanya nilai yang diketahui
            'sort'        => ['sometimes', 'string', 'in:price_asc,price_desc,rating_desc,rating_asc,latest'],

            // pagination: page mulai dari 1
            'page'        => ['sometimes', 'integer', 'min:1'],

            // limit dibatasi 1-100 untuk mencegah query yang mengambil terlalu banyak data
            'limit'       => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.max'         => 'Keyword terlalu panjang, maksimal 100 karakter.',
            'category_id.integer' => 'category_id harus berupa bilangan bulat.',
            'category_id.min'     => 'category_id harus bernilai positif.',
            'min_price.numeric'   => 'min_price harus berupa angka.',
            'min_price.min'       => 'min_price tidak boleh negatif.',
            'max_price.numeric'   => 'max_price harus berupa angka.',
            'max_price.gte'       => 'max_price harus lebih besar atau sama dengan min_price.',
            'min_rating.numeric'  => 'min_rating harus berupa angka.',
            'min_rating.min'      => 'min_rating minimal 0.',
            'min_rating.max'      => 'min_rating maksimal 5.',
            'sort.in'             => 'Nilai sort tidak valid. Gunakan: price_asc, price_desc, rating_desc, rating_asc, latest.',
            'page.integer'        => 'page harus berupa bilangan bulat.',
            'page.min'            => 'page minimal 1.',
            'limit.integer'       => 'limit harus berupa bilangan bulat.',
            'limit.min'           => 'limit minimal 1.',
            'limit.max'           => 'limit maksimal 100.',
        ];
    }

    /**
     * Override failedValidation agar mengembalikan JSON 422 konsisten
     * dengan format API yang dipakai di seluruh proyek.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Parameter pencarian tidak valid.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
