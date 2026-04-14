<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'data_type',
        'unit',
        'is_required',
        'is_filterable',
        'validation_rules',
        'default_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'is_active' => 'boolean',
            'validation_rules' => 'array',
            'default_value' => 'array',
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: hanya atribut aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: hanya atribut yang bisa difilter.
     */
    public function scopeFilterable(Builder $query): Builder
    {
        return $query->where('is_filterable', true);
    }

    /**
     * Scope: cari berdasarkan code.
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Opsi-opsi untuk atribut bertipe select/multiselect.
     */
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order');
    }

    /**
     * Kategori-kategori yang menggunakan atribut ini.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attributes')
                    ->withPivot('is_required', 'sort_order')
                    ->withTimestamps();
    }

    /**
     * Nilai atribut ini pada produk-produk.
     */
    public function productValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Cek apakah atribut bertipe select (punya options).
     */
    public function isSelectType(): bool
    {
        return in_array($this->data_type, ['select', 'multiselect']);
    }

    /**
     * Mendapatkan nama kolom value yang sesuai dengan data_type.
     * Digunakan di ProductAttributeValue untuk menentukan kolom mana yang diisi.
     */
    public function getValueColumn(): string
    {
        return match ($this->data_type) {
            'text' => 'value_text',
            'integer' => 'value_integer',
            'decimal' => 'value_decimal',
            'boolean' => 'value_boolean',
            'date' => 'value_date',
            'select' => 'attribute_option_id',
            'multiselect', 'json' => 'value_json',
            default => 'value_text',
        };
    }
}
