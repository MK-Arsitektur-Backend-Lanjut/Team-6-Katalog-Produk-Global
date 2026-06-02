<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | TTL (time-to-live) untuk cache Redis dalam detik.
    | Default: 3600 (1 jam). Sesuaikan berdasarkan traffic pattern.
    |
    */

    'cache_ttl' => (int) env('CATALOG_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Search Cache Configuration
    |--------------------------------------------------------------------------
    |
    | TTL pendek untuk cache endpoint search optimization seperti autocomplete,
    | facets, suggest, dan stats. Dibuat lebih pendek dari cache detail produk
    | agar hasil search tetap cukup fresh saat katalog sering berubah.
    |
    */

    'search_cache_ttl' => (int) env('CATALOG_SEARCH_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | Jumlah produk yang diproses per batch saat mass rebuild/sync.
    | Terlalu besar → memory issue. Terlalu kecil → terlalu banyak job.
    |
    */

    'batch_size' => (int) env('CATALOG_BATCH_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Outbox Configuration
    |--------------------------------------------------------------------------
    |
    | max_retries: Berapa kali event boleh di-retry sebelum di-skip.
    | poll_limit: Jumlah event yang diambil per polling cycle.
    |
    */

    'outbox' => [
        'max_retries' => (int) env('CATALOG_OUTBOX_MAX_RETRIES', 3),
        'poll_limit' => (int) env('CATALOG_OUTBOX_POLL_LIMIT', 100),
    ],

];
