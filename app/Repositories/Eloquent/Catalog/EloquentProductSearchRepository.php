<?php

namespace App\Repositories\Eloquent\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\Category;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentProductSearchRepository
{
    protected Product $model;

    public function __construct(Product $model)
    {
        $this->model = $model;
    }

    /**
     * Dynamic search with filters, sorting and pagination.
     * @param array $params
     * @return LengthAwarePaginator
     */
    public function search(array $params): LengthAwarePaginator
    {
        $table = $this->model->getTable();

        $query = $this->model->newQuery()->where('status', 'active');

        // Select only needed columns if exist
        $wanted = ['id', 'sku', 'slug', 'name', 'price', 'rating_avg', 'created_at'];
        $select = [];
        foreach ($wanted as $c) {
            if (Schema::hasColumn($table, $c)) {
                $select[] = "$table.$c";
            }
        }
        if (!empty($select)) {
            $query->select($select);
        }

        // Eager load categories and images to avoid N+1
        $query->with([
            'categories' => function ($q) {
                $q->select('categories.id', 'categories.name');
            },
            'images' => function ($q) {
                $q->select('product_images.id', 'product_images.product_id', 'product_images.url', 'product_images.position');
            }
        ]);

        // keyword search (name preferred)
        if (!empty($params['keyword'])) {
            $kw = trim($params['keyword']);

            // Prefer FULLTEXT MATCH...AGAINST when available on products.name
            $useFulltext = false;
            try {
                $res = DB::select("SHOW INDEX FROM {$table} WHERE Column_name = 'name' AND Index_type = 'FULLTEXT'");
                if (!empty($res)) {
                    $useFulltext = true;
                }
            } catch (\Throwable $e) {
                // ignore, fallback to LIKE
            }

            if ($useFulltext) {
                // use boolean mode for partial match and relevance
                $query->when($kw, fn($q) => $q->whereRaw("MATCH({$table}.name) AGAINST(? IN BOOLEAN MODE)", [$kw]));
            } elseif (Schema::hasColumn($table, 'name')) {
                $query->when($kw, fn($q) => $q->where("{$table}.name", 'like', "%{$kw}%"));
            } elseif (Schema::hasColumn($table, 'slug')) {
                $query->when($kw, fn($q) => $q->where("{$table}.slug", 'like', "%{$kw}%"));
            }
        }

        // Category filter — include descendants via closure table if available
        if (!empty($params['category_id'])) {
            $catId = (int) $params['category_id'];
            $ids = [$catId];

            $category = Category::find($catId);
            if ($category) {
                try {
                    $desc = $category->descendants()->pluck('id')->toArray();
                    if (!empty($desc)) {
                        $ids = array_values(array_unique($desc));
                    }
                } catch (\Throwable $e) {
                    // ignore and fallback to single id
                }
            }

            $query->whereHas('categories', fn($q) => $q->whereIn('categories.id', $ids));
        }

        // Price filters
        if (!empty($params['min_price']) && Schema::hasColumn($table, 'price')) {
            $query->where('price', '>=', (float)$params['min_price']);
        }
        if (!empty($params['max_price']) && Schema::hasColumn($table, 'price')) {
            $query->where('price', '<=', (float)$params['max_price']);
        }

        // Rating filter
        if (!empty($params['rating']) && Schema::hasColumn($table, 'rating_avg')) {
            $query->where('rating_avg', '>=', (float)$params['rating']);
        }

        // Sorting
        $sortBy = $params['sort_by'] ?? 'latest';
        $order = strtolower($params['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'price' && Schema::hasColumn($table, 'price')) {
            $query->orderBy('price', $order);
        } elseif ($sortBy === 'rating' && Schema::hasColumn($table, 'rating_avg')) {
            $query->orderBy('rating_avg', $order);
        } else {
            if (Schema::hasColumn($table, 'created_at')) {
                $query->orderBy('created_at', $order);
            } else {
                $query->orderBy('id', $order);
            }
        }

        // Pagination
        $limit = isset($params['limit']) ? max(1, min(100, (int)$params['limit'])) : 15;
        $page = isset($params['page']) ? (int)$params['page'] : null;

        return $query->paginate($limit, ['*'], 'page', $page);
    }
}
