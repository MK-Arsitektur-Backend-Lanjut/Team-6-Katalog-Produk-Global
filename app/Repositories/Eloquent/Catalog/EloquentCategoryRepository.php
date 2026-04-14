<?php

namespace App\Repositories\Eloquent\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\CategoryClosure;
use App\Models\Catalog\ProductCategory;
use App\Repositories\Contracts\Catalog\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Category
    {
        return Category::find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Category
    {
        return Category::bySlug($slug)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getRoots(): Collection
    {
        return Category::active()
            ->root()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * {@inheritdoc}
     *
     * Menggunakan eager loading recursive (childrenRecursive)
     * untuk mengambil seluruh tree dalam minimal query.
     */
    public function getTree(): Collection
    {
        return Category::active()
            ->root()
            ->with('childrenRecursive')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * {@inheritdoc}
     *
     * Query closure table: ambil semua rows dimana descendant_id = $categoryId,
     * lalu join ke categories. Diurutkan depth DESC → root dulu.
     */
    public function getAncestors(int $categoryId): Collection
    {
        $ancestorIds = CategoryClosure::where('descendant_id', $categoryId)
            ->orderBy('depth', 'desc')
            ->pluck('ancestor_id');

        return Category::whereIn('id', $ancestorIds)
            ->orderByRaw('FIELD(id, ' . $ancestorIds->implode(',') . ')')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getDescendants(int $categoryId): Collection
    {
        $descendantIds = CategoryClosure::where('ancestor_id', $categoryId)
            ->where('depth', '>', 0)
            ->orderBy('depth')
            ->pluck('descendant_id');

        return Category::whereIn('id', $descendantIds)->get();
    }

    /**
     * {@inheritdoc}
     *
     * Breadcrumbs = ancestors diurutkan root → leaf.
     * Hanya mengambil kolom yang diperlukan (id, name, slug) untuk performa.
     */
    public function getBreadcrumbs(int $categoryId): Collection
    {
        $ancestorIds = CategoryClosure::where('descendant_id', $categoryId)
            ->orderBy('depth', 'desc')
            ->pluck('ancestor_id');

        if ($ancestorIds->isEmpty()) {
            return new Collection();
        }

        return Category::whereIn('id', $ancestorIds)
            ->select('id', 'name', 'slug')
            ->orderByRaw('FIELD(id, ' . $ancestorIds->implode(',') . ')')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        return $category->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Category $category): bool
    {
        return $category->delete();
    }

    /**
     * {@inheritdoc}
     *
     * Untuk kategori baru, closure entries yang perlu dibuat:
     * 1. Self-reference: (id, id, depth=0)
     * 2. Semua ancestor parent: copy parent's ancestors dengan depth+1
     *
     * Contoh: buat "Android" dengan parent "Smartphone"
     * - Smartphone punya closure: (Elektronik→Smartphone, depth=1), (Smartphone→Smartphone, depth=0)
     * - Android akan mendapat:
     *   - (Android→Android, depth=0) ← self
     *   - (Smartphone→Android, depth=1) ← copy dari parent self dengan depth+1
     *   - (Elektronik→Android, depth=2) ← copy dari parent ancestor dengan depth+1
     */
    public function insertClosureEntries(Category $category): void
    {
        // 1. Self-reference
        CategoryClosure::create([
            'ancestor_id' => $category->id,
            'descendant_id' => $category->id,
            'depth' => 0,
        ]);

        // 2. Copy ancestors dari parent (jika punya parent)
        if ($category->parent_id) {
            $parentClosures = CategoryClosure::where('descendant_id', $category->parent_id)->get();

            foreach ($parentClosures as $closure) {
                CategoryClosure::create([
                    'ancestor_id' => $closure->ancestor_id,
                    'descendant_id' => $category->id,
                    'depth' => $closure->depth + 1,
                ]);
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * Rebuild saat parent berubah:
     * 1. Ambil semua descendant dari kategori ini (termasuk dirinya)
     * 2. Hapus semua closure entries dimana descendant = kategori/descendants
     *    KECUALI self-reference dan internal subtree relations
     * 3. Re-insert entries baru berdasarkan parent baru
     *
     * Approach yang lebih sederhana: hapus semua entries dimana descendant
     * termasuk subtree, lalu rebuild dari akar.
     */
    public function rebuildClosureEntries(Category $category): void
    {
        // Ambil semua descendant IDs (termasuk self)
        $subtreeIds = CategoryClosure::where('ancestor_id', $category->id)
            ->pluck('descendant_id');

        // Hapus semua entries dimana descendant ada di subtree
        CategoryClosure::whereIn('descendant_id', $subtreeIds)->delete();

        // Rebuild: untuk setiap node di subtree, insert ulang
        $subtreeCategories = Category::whereIn('id', $subtreeIds)
            ->orderBy('depth')
            ->get();

        foreach ($subtreeCategories as $node) {
            // Self-reference
            CategoryClosure::create([
                'ancestor_id' => $node->id,
                'descendant_id' => $node->id,
                'depth' => 0,
            ]);

            // Ancestors dari parent
            if ($node->parent_id) {
                $parentClosures = CategoryClosure::where('descendant_id', $node->parent_id)->get();

                foreach ($parentClosures as $closure) {
                    CategoryClosure::create([
                        'ancestor_id' => $closure->ancestor_id,
                        'descendant_id' => $node->id,
                        'depth' => $closure->depth + 1,
                    ]);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProductIdsByCategory(int $categoryId): SupportCollection
    {
        return ProductCategory::where('category_id', $categoryId)
            ->pluck('product_id');
    }
}
