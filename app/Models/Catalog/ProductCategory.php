<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCategory extends Model
{
    /**
     * Pivot table product_categories menggunakan composite primary key.
     * Tidak ada auto-increment id.
     */
    public $incrementing = false;

    protected $table = 'product_categories';

    protected $fillable = [
        'product_id',
        'category_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'category_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Produk yang terkait.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Kategori yang terkait.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
