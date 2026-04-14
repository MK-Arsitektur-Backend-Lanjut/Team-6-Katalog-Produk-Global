<?php

namespace App\Repositories\Eloquent\Catalog;

use App\Models\Catalog\Attribute;
use App\Models\Catalog\CategoryAttribute;
use App\Models\Catalog\CategoryClosure;
use App\Repositories\Contracts\Catalog\AttributeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentAttributeRepository implements AttributeRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Attribute
    {
        return Attribute::find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByCode(string $code): ?Attribute
    {
        return Attribute::byCode($code)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Attribute
    {
        return Attribute::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Attribute $attribute, array $data): Attribute
    {
        $attribute->update($data);
        return $attribute->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function getByCategoryIds(array $categoryIds): Collection
    {
        return Attribute::active()
            ->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('category_id', $categoryIds);
            })
            ->with(['options' => fn($q) => $q->active()->orderBy('sort_order')])
            ->get();
    }

    /**
     * {@inheritdoc}
     *
     * Langkah:
     * 1. Ambil semua ancestor IDs dari closure table (termasuk self, depth=0)
     * 2. Ambil semua category_attributes untuk ancestor tersebut
     * 3. Join ke attributes, urutkan berdasarkan depth ancestor (root dulu) → sort_order
     * 4. Deduplicate: jika atribut yang sama muncul di multiple levels,
     *    gunakan definisi dari level terdalam (closest ancestor)
     */
    public function getInheritedAttributes(int $categoryId): Collection
    {
        // Ambil ancestor IDs terurut dari root ke self
        $ancestorIds = CategoryClosure::where('descendant_id', $categoryId)
            ->orderBy('depth', 'desc')
            ->pluck('ancestor_id')
            ->toArray();

        if (empty($ancestorIds)) {
            return new Collection();
        }

        // Ambil semua atribut dari semua ancestor categories
        $categoryAttributes = CategoryAttribute::whereIn('category_id', $ancestorIds)
            ->with(['attribute' => fn($q) => $q->active()->with(['options' => fn($oq) => $oq->active()])])
            ->get();

        // Group by attribute_id, prioritaskan definisi dari level terdalam
        // (index terakhir dalam ancestorIds = closest = self)
        $ancestorOrder = array_flip($ancestorIds);
        $uniqueAttributes = $categoryAttributes
            ->filter(fn($ca) => $ca->attribute !== null)
            ->sortBy(function ($ca) use ($ancestorOrder) {
                return $ancestorOrder[$ca->category_id] ?? 999;
            })
            ->unique('attribute_id')
            ->values();

        // Return sebagai Collection of Attribute models
        return new Collection(
            $uniqueAttributes->map(fn($ca) => $ca->attribute)->all()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function syncCategoryAttributes(int $categoryId, array $attributes): void
    {
        // Menggunakan sync() pada relasi BelongsToMany dari Category model
        $category = \App\Models\Catalog\Category::findOrFail($categoryId);
        $category->attributes()->sync($attributes);
    }
}
