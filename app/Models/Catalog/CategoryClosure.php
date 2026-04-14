<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryClosure extends Model
{
    /**
     * Closure Table tidak memerlukan auto-increment primary key.
     * Primary key adalah composite (ancestor_id, descendant_id).
     */
    public $incrementing = false;
    public $timestamps = false;

    protected $table = 'category_closure';

    protected $fillable = [
        'ancestor_id',
        'descendant_id',
        'depth',
    ];

    protected function casts(): array
    {
        return [
            'ancestor_id' => 'integer',
            'descendant_id' => 'integer',
            'depth' => 'integer',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Kategori ancestor dalam relasi ini.
     */
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'ancestor_id');
    }

    /**
     * Kategori descendant dalam relasi ini.
     */
    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'descendant_id');
    }
}
