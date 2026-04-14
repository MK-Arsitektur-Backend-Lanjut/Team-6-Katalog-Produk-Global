<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    protected $fillable = [
        'product_id',
        'attribute_id',
        'attribute_option_id',
        'value_text',
        'value_integer',
        'value_decimal',
        'value_boolean',
        'value_date',
        'value_json',
        'display_value',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'attribute_id' => 'integer',
            'attribute_option_id' => 'integer',
            'value_integer' => 'integer',
            'value_decimal' => 'decimal:4',
            'value_boolean' => 'boolean',
            'value_date' => 'date',
            'value_json' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Produk pemilik nilai atribut ini.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Definisi atribut yang direferensikan.
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Opsi yang dipilih (untuk atribut bertipe select).
     * Nullable — hanya terisi jika data_type = 'select'.
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class, 'attribute_option_id');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Mendapatkan nilai aktual berdasarkan data_type atribut.
     * Mengembalikan value dari kolom yang sesuai.
     */
    public function getResolvedValue(): mixed
    {
        if (!$this->attribute) {
            return null;
        }

        return match ($this->attribute->data_type) {
            'text' => $this->value_text,
            'integer' => $this->value_integer,
            'decimal' => $this->value_decimal,
            'boolean' => $this->value_boolean,
            'date' => $this->value_date,
            'select' => $this->option?->label,
            'multiselect', 'json' => $this->value_json,
            default => $this->value_text,
        };
    }

    /**
     * Mendapatkan display value.
     * Prioritas: display_value (pre-computed) → resolved value → null.
     */
    public function getDisplayString(): ?string
    {
        if ($this->display_value) {
            return $this->display_value;
        }

        $resolved = $this->getResolvedValue();

        if (is_null($resolved)) {
            return null;
        }

        if (is_array($resolved)) {
            return implode(', ', $resolved);
        }

        if (is_bool($resolved)) {
            return $resolved ? 'Yes' : 'No';
        }

        return (string) $resolved;
    }
}
