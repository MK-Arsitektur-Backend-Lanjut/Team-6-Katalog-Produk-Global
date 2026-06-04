<?php

namespace App\Services\Catalog;

use App\Repositories\Eloquent\Catalog\EloquentProductSearchRepository;

class ProductSearchService
{
    protected EloquentProductSearchRepository $repo;

    public function __construct(EloquentProductSearchRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Normalize params and call repository
     * @param array $params
     */
    public function search(array $params)
    {
        $params = $this->normalizeAliases($params);

        $allowed = ['keyword','category_id','min_price','max_price','rating','sort_by','order','page','limit'];
        $clean = [];
        foreach ($allowed as $k) {
            if (isset($params[$k]) && $params[$k] !== '') {
                $clean[$k] = $params[$k];
            }
        }

        if (isset($clean['limit'])) {
            $clean['limit'] = max(1, min(100, (int)$clean['limit']));
        }
        if (isset($clean['page'])) {
            $clean['page'] = max(1, (int)$clean['page']);
        }

        return $this->repo->search($clean);
    }

    protected function normalizeAliases(array $params): array
    {
        if (isset($params['min_rating']) && !isset($params['rating'])) {
            $params['rating'] = $params['min_rating'];
        }

        if (!empty($params['sort']) && empty($params['sort_by'])) {
            [$sortBy, $order] = match ($params['sort']) {
                'price_asc' => ['price', 'asc'],
                'price_desc' => ['price', 'desc'],
                'rating_desc' => ['rating', 'desc'],
                'rating_asc' => ['rating', 'asc'],
                default => ['latest', 'desc'],
            };

            $params['sort_by'] = $sortBy;
            $params['order'] = $params['order'] ?? $order;
        }

        return $params;
    }
}
