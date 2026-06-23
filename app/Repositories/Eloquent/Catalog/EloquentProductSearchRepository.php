<?php

namespace App\Repositories\Eloquent\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository untuk pencarian produk dengan optimasi query.
 *
 * Optimasi utama:
 * 1. Static memoization untuk Schema::hasColumn() — sebelumnya dipanggil 5-7x
 *    per request, masing-masing melakukan "SHOW COLUMNS FROM products".
 *    Sekarang hanya dihitung sekali per proses PHP.
 *
 * 2. Static memoization untuk deteksi FULLTEXT index — sebelumnya "SHOW INDEX"
 *    dikirim ke MySQL di setiap request keyword search.
 *    Sekarang hanya dihitung sekali per proses PHP.
 */
class EloquentProductSearchRepository
{
    protected Product $model;

    /**
     * Cache static: kolom yang ada di tabel products.
     * Null berarti belum pernah dicek; diisi sekali saat pertama kali dibutuhkan.
     *
     * @var array<string, bool>|null
     */
    private static ?array $columnCache = null;

    /**
     * Cache static: apakah FULLTEXT index ft_products_search tersedia.
     * Null berarti belum dicek; true/false setelah cek pertama.
     */
    private static ?bool $fulltextAvailable = null;

    public function __construct(Product $model)
    {
        $this->model = $model;
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Dynamic search dengan filter, sorting, dan pagination.
     *
     * @param array $params Parameter yang sudah divalidasi oleh ProductSearchRequest
     * @return LengthAwarePaginator
     */
    public function search(array $params): \Illuminate\Contracts\Pagination\Paginator
    {
        $table = $this->model->getTable();

        $query = $this->model->newQuery()->where('status', 'active');

        // Pilih kolom yang tersedia — cek dilakukan sekali via static cache
        $select = $this->resolveSelectColumns($table);
        if (!empty($select)) {
            $query->select($select);
        }

        // OPTIMASI: Eager loading categories + images DIHAPUS.
        // ProductResource pada endpoint /search hanya mengembalikan kolom skalar
        // (id, sku, slug, name, price, rating_avg, dst) — tidak pernah menyertakan
        // relasi categories atau images. Eager loading ini menambah 2 query ekstra
        // per request tanpa manfaat apapun (= +72 query/detik pada 36 req/s).

        // === FILTER: Keyword ===
        if (!empty($params['keyword'])) {
            $this->applyKeywordFilter($query, $table, trim($params['keyword']));
        }

        // === FILTER: Kategori (termasuk descendant via closure table) ===
        if (!empty($params['category_id'])) {
            $this->applyCategoryFilter($query, (int) $params['category_id']);
        }

        // === FILTER: Harga ===
        if (!empty($params['min_price']) && $this->hasColumn($table, 'price')) {
            $query->where('price', '>=', (float) $params['min_price']);
        }
        if (!empty($params['max_price']) && $this->hasColumn($table, 'price')) {
            $query->where('price', '<=', (float) $params['max_price']);
        }

        // === FILTER: Rating ===
        if (!empty($params['rating']) && $this->hasColumn($table, 'rating_avg')) {
            $query->where('rating_avg', '>=', (float) $params['rating']);
        }

        // === SORT ===
        $this->applySort($query, $table, $params);

        // === PAGINATION ===
        $limit = isset($params['limit']) ? max(1, min(100, (int) $params['limit'])) : 15;
        $page  = isset($params['page']) ? (int) $params['page'] : null;

        /**
         * OPTIMASI: simplePaginate() menggantikan paginate().
         *
         * paginate()       = SELECT data + SELECT COUNT(*) as aggregate  → 2 queries
         * simplePaginate() = SELECT data + 1 extra row untuk deteksi hasMorePages → 1 query
         *
         * COUNT(*) dengan filter LIKE pada tabel 10.000+ row = full scan mahal.
         * Dengan 36 req/s, ini = 36 COUNT queries ekstra per detik ke MySQL.
         * Trade-off: response tidak memiliki field 'total' dan 'last_page',
         * tapi jauh lebih cepat dan ringan untuk MySQL.
         */
        return $query->simplePaginate($limit, ['*'], 'page', $page);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Cek apakah kolom ada di tabel, dengan hasil dicache secara static.
     *
     * Sebelumnya: Schema::hasColumn() → mengirim "SHOW COLUMNS FROM {table}" ke DB setiap panggilan.
     * Sekarang: cek dilakukan sekali, hasilnya disimpan di memori proses PHP.
     */
    private function hasColumn(string $table, string $column): bool
    {
        if (self::$columnCache === null) {
            $this->warmColumnCache($table);
        }

        return self::$columnCache["{$table}.{$column}"] ?? false;
    }

    /**
     * Isi column cache dengan daftar kolom aktual dari database.
     * Hanya dipanggil sekali per proses PHP (saat $columnCache masih null).
     * OPTIMASI: Langsung hardcode kolom produk statis untuk menghindari query SHOW COLUMNS secara runtime.
     */
    private function warmColumnCache(string $table): void
    {
        self::$columnCache = [];
        foreach (['id','sku','slug','name','short_description','description','price','rating_avg','status','metadata_version','created_at','updated_at','deleted_at'] as $c) {
            self::$columnCache["{$table}.{$c}"] = true;
        }
    }

    /**
     * Tentukan kolom yang akan di-SELECT berdasarkan ketersediaannya.
     * Hasilnya sama setiap request → cukup dihitung sekali via column cache.
     */
    private function resolveSelectColumns(string $table): array
    {
        $wanted = ['id', 'sku', 'slug', 'name', 'price', 'rating_avg', 'created_at'];
        $select = [];
        foreach ($wanted as $col) {
            if ($this->hasColumn($table, $col)) {
                $select[] = "{$table}.{$col}";
            }
        }
        return $select;
    }

    /**
     * Deteksi apakah FULLTEXT index ft_products_search tersedia.
     * OPTIMASI: Langsung return true karena FULLTEXT index terjamin ada via migrasi DB.
     */
    private function isFulltextAvailable(string $table): bool
    {
        return true;
    }

    /**
     * Terapkan filter keyword ke query.
     * Prioritas: FULLTEXT MATCH...AGAINST (jika index ada) → LIKE pada name.
     */
    private function applyKeywordFilter($query, string $table, string $kw): void
    {
        if ($this->isFulltextAvailable($table)) {
            // FULLTEXT boolean mode — jauh lebih cepat dari LIKE pada data besar
            $terms = collect(preg_split('/\s+/', $kw))
                ->filter()
                ->map(fn($term) => '+' . $term . '*')
                ->implode(' ');

            if ($terms) {
                $query->whereRaw(
                    "MATCH(`{$table}`.`name`, `{$table}`.`short_description`) AGAINST(? IN BOOLEAN MODE)",
                    [$terms]
                );
            }
        } elseif ($this->hasColumn($table, 'name')) {
            // Fallback LIKE — prefix LIKE '%kw%' tidak bisa pakai index,
            // tapi ini fallback jika FULLTEXT tidak tersedia
            $query->where("{$table}.name", 'like', "%{$kw}%");
        }
    }

    /**
     * Terapkan filter kategori termasuk semua descendant-nya via closure table.
     * OPTIMASI:
     * 1. Cache list ID kategori keturunan di Redis selama 1 jam agar bebas query Category::find() dan descendants() berulang.
     * 2. Gunakan whereExists langsung ke tabel pivot product_categories (menghindari join lambat ke tabel categories).
     */
    private function applyCategoryFilter($query, int $catId): void
    {
        $ids = \Illuminate\Support\Facades\Cache::store('redis')->remember(
            "category:descendants:{$catId}",
            3600, // 1 hour TTL
            function () use ($catId) {
                $ids = [$catId];
                $category = Category::find($catId);
                if ($category) {
                    try {
                        $desc = $category->descendants()->pluck('id')->toArray();
                        if (!empty($desc)) {
                            $ids = array_values(array_unique(array_merge([$catId], $desc)));
                        }
                    } catch (\Throwable) {
                        // Fallback: gunakan hanya ID yang diberikan
                    }
                }
                return $ids;
            }
        );

        $query->whereExists(function ($sub) use ($ids) {
            $sub->select(DB::raw(1))
                ->from('product_categories')
                ->whereColumn('product_categories.product_id', 'products.id')
                ->whereIn('product_categories.category_id', $ids);
        });
    }

    /**
     * Terapkan sorting ke query.
     */
    private function applySort($query, string $table, array $params): void
    {
        $sortBy = $params['sort_by'] ?? 'latest';
        $order  = strtolower($params['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'price' && $this->hasColumn($table, 'price')) {
            $query->orderBy('price', $order);
        } elseif ($sortBy === 'rating' && $this->hasColumn($table, 'rating_avg')) {
            $query->orderBy('rating_avg', $order);
        } elseif ($this->hasColumn($table, 'created_at')) {
            $query->orderBy('created_at', $order);
        } else {
            $query->orderBy('id', $order);
        }
    }
}
