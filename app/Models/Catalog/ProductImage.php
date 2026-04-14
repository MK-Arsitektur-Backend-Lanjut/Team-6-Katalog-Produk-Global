<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'url',
        'alt_text',
        'position',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: hanya gambar utama.
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Produk pemilik gambar ini.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
