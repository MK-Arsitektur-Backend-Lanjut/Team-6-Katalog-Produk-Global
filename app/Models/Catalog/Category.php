<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'path',
        'depth',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'depth' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: hanya kategori aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: hanya kategori root (tanpa parent).
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: cari berdasarkan slug.
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    // =========================================================================
    // RELATIONSHIPS — Adjacency List (parent_id)
    // =========================================================================

    /**
     * Kategori induk langsung.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Anak-anak langsung (direct children), diurutkan berdasarkan sort_order.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Recursive children — untuk eager loading tree secara nested.
     * Contoh penggunaan: Category::with('childrenRecursive')->root()->get()
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    // =========================================================================
    // RELATIONSHIPS — Closure Table
    // =========================================================================

    /**
     * Semua ancestor (termasuk diri sendiri via depth=0) melalui closure table.
     * Diurutkan dari root ke leaf (depth DESC → ancestor terjauh dulu).
     */
    public function ancestors(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'descendant_id',
            'ancestor_id'
        )->withPivot('depth')
         ->orderByPivot('depth', 'desc');
    }

    /**
     * Semua descendant (termasuk diri sendiri via depth=0) melalui closure table.
     */
    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'ancestor_id',
            'descendant_id'
        )->withPivot('depth')
         ->orderByPivot('depth', 'asc');
    }

    /**
     * Closure entries dimana kategori ini menjadi ancestor.
     */
    public function closureAsAncestor(): HasMany
    {
        return $this->hasMany(CategoryClosure::class, 'ancestor_id');
    }

    /**
     * Closure entries dimana kategori ini menjadi descendant.
     */
    public function closureAsDescendant(): HasMany
    {
        return $this->hasMany(CategoryClosure::class, 'descendant_id');
    }

    // =========================================================================
    // RELATIONSHIPS — Products & Attributes
    // =========================================================================

    /**
     * Produk-produk yang memiliki kategori ini (many-to-many).
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    /**
     * Atribut-atribut yang didefinisikan langsung pada kategori ini (bukan inherited).
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'category_attributes')
                    ->withPivot('is_required', 'sort_order')
                    ->withTimestamps()
                    ->orderByPivot('sort_order');
    }

    /**
     * Record pivot category_attributes.
     */
    public function categoryAttributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Cek apakah kategori adalah root.
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Cek apakah kategori punya children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }
}
