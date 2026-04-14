<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Category;
use App\Repositories\Contracts\Catalog\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service untuk mengelola hierarki kategori.
 *
 * Responsibility:
 * - CRUD kategori dengan manajemen closure table otomatis
 * - Build tree, breadcrumbs, ancestor/descendant queries
 * - Update path dan depth saat parent berubah
 * - Koordinasi dengan CatalogCacheService untuk invalidation
 */
class CategoryHierarchyService
{
    public function __construct(
        protected CategoryRepositoryInterface $categoryRepo,
        protected CatalogCacheService $cacheService,
        protected CatalogOutboxService $outboxService,
    ) {}

    // =========================================================================
    // READ OPERATIONS
    // =========================================================================

    /**
     * Ambil category tree lengkap (dari cache jika tersedia).
     */
    public function getTree(): Collection
    {
        $cacheKey = $this->cacheService->categoryTreeKey();

        return $this->cacheService->remember($cacheKey, function () {
            return $this->categoryRepo->getTree();
        });
    }

    /**
     * Ambil kategori berdasarkan slug (dari cache jika tersedia).
     */
    public function findBySlug(string $slug): ?Category
    {
        $cacheKey = $this->cacheService->categorySlugKey($slug);

        return $this->cacheService->remember($cacheKey, function () use ($slug) {
            return $this->categoryRepo->findBySlug($slug);
        });
    }

    /**
     * Ambil kategori berdasarkan ID.
     */
    public function findById(int $id): ?Category
    {
        return $this->categoryRepo->findById($id);
    }

    /**
     * Ambil breadcrumbs untuk kategori (dari cache jika tersedia).
     * Return array of {id, name, slug} dari root ke kategori.
     */
    public function getBreadcrumbs(int $categoryId): Collection
    {
        $cacheKey = $this->cacheService->categoryBreadcrumbsKey($categoryId);

        return $this->cacheService->remember($cacheKey, function () use ($categoryId) {
            return $this->categoryRepo->getBreadcrumbs($categoryId);
        });
    }

    /**
     * Ambil semua ancestor dari kategori (termasuk self).
     */
    public function getAncestors(int $categoryId): Collection
    {
        return $this->categoryRepo->getAncestors($categoryId);
    }

    /**
     * Ambil semua descendant dari kategori.
     */
    public function getDescendants(int $categoryId): Collection
    {
        return $this->categoryRepo->getDescendants($categoryId);
    }

    // =========================================================================
    // WRITE OPERATIONS
    // =========================================================================

    /**
     * Buat kategori baru.
     *
     * Flow:
     * 1. Hitung depth berdasarkan parent
     * 2. Build materialized path
     * 3. Create kategori
     * 4. Insert closure table entries
     * 5. Invalidate cache
     * 6. Record outbox event
     */
    public function createCategory(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            // Tentukan depth dan path berdasarkan parent
            $parent = null;
            if (!empty($data['parent_id'])) {
                $parent = $this->categoryRepo->findById($data['parent_id']);
            }

            $data['depth'] = $parent ? $parent->depth + 1 : 0;
            $data['path'] = $this->buildPath($data['slug'], $parent);

            // Create kategori
            $category = $this->categoryRepo->create($data);

            // Insert closure table entries
            $this->categoryRepo->insertClosureEntries($category);

            // Invalidate cache
            $this->cacheService->invalidateCategoryTree();

            // Record outbox
            $this->outboxService->recordCategoryCreated($category->id, [
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
            ]);

            return $category;
        });
    }

    /**
     * Update kategori.
     *
     * Jika parent_id berubah:
     * 1. Validasi tidak circular (parent bukan descendant dari dirinya)
     * 2. Update depth, path
     * 3. Rebuild closure table entries
     * 4. Update depth/path semua descendants
     */
    public function updateCategory(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $parentChanged = isset($data['parent_id']) && $data['parent_id'] !== $category->parent_id;

            // Jika parent berubah, validasi dan rebuild hierarchy
            if ($parentChanged) {
                $newParent = null;
                if ($data['parent_id']) {
                    $newParent = $this->categoryRepo->findById($data['parent_id']);

                    // Validasi: parent baru tidak boleh descendant dari kategori ini
                    if ($this->isDescendant($category->id, $data['parent_id'])) {
                        throw new \InvalidArgumentException(
                            'Cannot set a descendant as parent (circular reference).'
                        );
                    }
                }

                $data['depth'] = $newParent ? $newParent->depth + 1 : 0;
                $slug = $data['slug'] ?? $category->slug;
                $data['path'] = $this->buildPath($slug, $newParent);
            }

            // Update kategori
            $category = $this->categoryRepo->update($category, $data);

            // Rebuild closure table dan descendants jika parent berubah
            if ($parentChanged) {
                $this->categoryRepo->rebuildClosureEntries($category);
                $this->rebuildDescendantPaths($category);
            }

            // Invalidate cache
            $this->cacheService->invalidateCategory($category->id, $category->slug);

            // Record outbox
            $this->outboxService->recordCategoryUpdated($category->id, array_keys($data));

            return $category;
        });
    }

    /**
     * Soft delete kategori.
     *
     * Validasi:
     * - Kategori yang masih punya produk aktif sebaiknya di-inactivate, bukan dihapus
     * - Kategori yang punya children aktif tidak bisa dihapus
     */
    public function deleteCategory(Category $category): bool
    {
        return DB::transaction(function () use ($category) {
            // Cek apakah masih punya active children
            if ($category->hasChildren()) {
                $activeChildren = $category->children()->where('is_active', true)->exists();
                if ($activeChildren) {
                    throw new \InvalidArgumentException(
                        'Cannot delete category with active children. Deactivate or move children first.'
                    );
                }
            }

            // Soft delete
            $result = $this->categoryRepo->delete($category);

            // Invalidate cache
            $this->cacheService->invalidateCategory($category->id, $category->slug);

            // Record outbox
            $this->outboxService->recordCategoryDeleted($category->id);

            return $result;
        });
    }

    // =========================================================================
    // HIERARCHY HELPERS
    // =========================================================================

    /**
     * Cek apakah $possibleDescendantId adalah descendant dari $ancestorId.
     * Digunakan untuk validasi circular reference.
     */
    public function isDescendant(int $ancestorId, int $possibleDescendantId): bool
    {
        $descendants = $this->categoryRepo->getDescendants($ancestorId);
        return $descendants->contains('id', $possibleDescendantId);
    }

    /**
     * Build materialized path: "parent-slug/current-slug"
     */
    protected function buildPath(string $slug, ?Category $parent): string
    {
        if (!$parent) {
            return $slug;
        }

        return rtrim($parent->path, '/') . '/' . $slug;
    }

    /**
     * Rebuild depth dan path untuk semua descendants secara recursive.
     * Dipanggil setelah parent berubah.
     */
    protected function rebuildDescendantPaths(Category $category): void
    {
        $children = $category->children()->get();

        foreach ($children as $child) {
            $child->update([
                'depth' => $category->depth + 1,
                'path' => $this->buildPath($child->slug, $category),
            ]);

            // Recursive rebuild untuk grandchildren
            $this->rebuildDescendantPaths($child);
        }
    }
}
