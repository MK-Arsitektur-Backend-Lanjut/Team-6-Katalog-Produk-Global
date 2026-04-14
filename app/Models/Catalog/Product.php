<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'slug',
        'name',
        'short_description',
        'description',
        'price',
        'rating_avg',
        'status',
        'metadata_version',
        'metadata_snapshot',
    ];

    /**
     * Cast attributes ke tipe yang sesuai.
     * metadata_snapshot di-cast ke array agar bisa diakses langsung tanpa json_decode.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'metadata_version' => 'integer',
            'metadata_snapshot' => 'array',
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: hanya produk aktif (untuk public API).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: cari berdasarkan slug.
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope: cari berdasarkan SKU.
     */
    public function scopeBySku(Builder $query, string $sku): Builder
    {
        return $query->where('sku', $sku);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Gambar-gambar produk, diurutkan berdasarkan position.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * Semua kategori yang dimiliki produk (many-to-many via product_categories).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    /**
     * Kategori utama produk (hanya satu, is_primary = true).
     * Menggunakan BelongsToMany karena relasinya melalui pivot table.
     */
    public function primaryCategory(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
                    ->withPivot('is_primary')
                    ->wherePivot('is_primary', true);
    }

    /**
     * Record pivot product_categories (untuk akses langsung tanpa join).
     */
    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /**
     * Nilai atribut produk (EAV pattern).
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Cek apakah produk aktif.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Ambil kategori utama (shortcut).
     * Return null jika belum di-assign.
     */
    public function getPrimaryCategoryAttribute(): ?Category
    {
        return $this->primaryCategory->first();
    }
}
