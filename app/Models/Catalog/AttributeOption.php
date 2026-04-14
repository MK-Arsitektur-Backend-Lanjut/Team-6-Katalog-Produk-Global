<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AttributeOption extends Model
{
    protected $fillable = [
        'attribute_id',
        'label',
        'value',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attribute_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: hanya opsi aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Atribut pemilik opsi ini.
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
