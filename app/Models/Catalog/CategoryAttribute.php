<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryAttribute extends Model
{
    /**
     * Pivot table category_attributes menggunakan composite primary key.
     * Tidak ada auto-increment id.
     */
    public $incrementing = false;

    protected $table = 'category_attributes';

    protected $fillable = [
        'category_id',
        'attribute_id',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'attribute_id' => 'integer',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Kategori yang memiliki atribut ini.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Atribut yang didefinisikan pada kategori.
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
