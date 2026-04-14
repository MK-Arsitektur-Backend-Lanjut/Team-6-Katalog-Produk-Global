<?php

namespace Database\Seeders;

use App\Models\Catalog\Category;
use App\Services\Catalog\CategoryHierarchyService;
use App\Services\Catalog\ProductAttributeSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductSeeder extends Seeder
{
    public function __construct(
        protected CategoryHierarchyService $categoryService,
        protected ProductAttributeSyncService $attributeSyncService,
    ) {}

    public function run(): void
    {
        $totalProducts = 10000;
        $batchSize = 100;

        DB::disableQueryLog();

        $categories = Category::all();
        if ($categories->isEmpty()) {
            $this->command?->warn('No categories found. Run CategorySeeder first.');
            return;
        }

        $categoryData = [];
        foreach ($categories as $cat) {
            $attributes = $this->attributeSyncService->resolveInheritedAttributes($cat->id);
            $attributes->loadMissing('options');

            $breadcrumbs = $this->categoryService->getBreadcrumbs($cat->id)
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ])
                ->toArray();

            $categoryData[$cat->id] = [
                'model' => $cat,
                'attributes' => $attributes,
                'breadcrumbs' => $breadcrumbs,
            ];
        }

        $categoryIds = array_keys($categoryData);
        $brands = ['Samsung', 'Apple', 'Asus', 'Sony', 'LG', 'Nike', 'Adidas', 'Puma', 'Ikea', 'Philips'];

        // Mulai dari max id + 1 agar lebih aman kalau seeder pernah dijalankan tanpa fresh.
        $productIdCounter = ((int) DB::table('products')->max('id')) + 1;

        $productsBatch = [];
        $categoriesBatch = [];
        $imagesBatch = [];
        $attributesBatch = [];

        $this->command?->info("Seeding {$totalProducts} products in batches of {$batchSize}...");
        $progressBar = $this->command?->getOutput()->createProgressBar($totalProducts);

        for ($i = 1; $i <= $totalProducts; $i++) {
            $now = now();

            $productId = $productIdCounter;
            $catId = $categoryIds[array_rand($categoryIds)];
            $catInfo = $categoryData[$catId];
            $catModel = $catInfo['model'];

            $brand = $brands[array_rand($brands)];
            $name = $brand . ' Product ' . Str::random(5) . ' - ' . $i;
            $slug = Str::slug($name) . '-' . $productId;
            $sku = 'SKU-' . str_pad((string) $productId, 6, '0', STR_PAD_LEFT);

            $price = rand(10, 1000) * 10000;
            $ratingAvg = rand(30, 50) / 10;

            $imgCount = rand(1, 3);
            $snapshotImages = [];

            for ($j = 0; $j < $imgCount; $j++) {
                $isPrimary = $j === 0;
                $url = "https://picsum.photos/seed/{$slug}-{$j}/800/800";

                $imagesBatch[] = [
                    'product_id' => $productId,
                    'url' => $url,
                    'alt_text' => $name . ' image ' . ($j + 1),
                    'is_primary' => $isPrimary,
                    'position' => $j,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $snapshotImages[] = [
                    'url' => $url,
                    'alt_text' => $name . ' image ' . ($j + 1),
                    'is_primary' => $isPrimary,
                ];
            }

            $snapshotAttributes = [];

            foreach ($catInfo['attributes'] as $attr) {
                $valData = [
                    'product_id' => $productId,
                    'attribute_id' => $attr->id,
                    'attribute_option_id' => null,
                    'display_value' => null,
                    'value_text' => null,
                    'value_integer' => null,
                    'value_decimal' => null,
                    'value_boolean' => null,
                    'value_date' => null,
                    'value_json' => null,
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $displayVal = null;

                if ($attr->code === 'brand') {
                    $valData['value_text'] = $brand;
                    $displayVal = $brand;
                } elseif ($attr->data_type === 'select' && $attr->options->isNotEmpty()) {
                    $option = $attr->options->random();
                    $valData['value_text'] = (string) $option->value;
                    $displayVal = $option->label;
                } elseif ($attr->data_type === 'decimal') {
                    $val = rand(100, 5000);
                    $valData['value_decimal'] = $val;
                    $displayVal = $val . ($attr->unit ? ' ' . $attr->unit : '');
                } elseif ($attr->data_type === 'boolean') {
                    $val = (bool) rand(0, 1);
                    $valData['value_boolean'] = $val;
                    $displayVal = $val ? 'Yes' : 'No';
                } else {
                    $val = 'Value ' . Str::random(3);
                    $valData['value_text'] = $val;
                    $displayVal = $val;
                }

                $valData['display_value'] = $displayVal;
                $attributesBatch[] = $valData;

                $snapshotAttributes[] = [
                    'code' => $attr->code,
                    'name' => $attr->name,
                    'value' => $displayVal,
                ];
            }

            $categoriesBatch[] = [
                'product_id' => $productId,
                'category_id' => $catId,
                'is_primary' => true,
            ];

            $snapshot = [
                'product' => [
                    'id' => $productId,
                    'sku' => $sku,
                    'slug' => $slug,
                    'name' => $name,
                    'short_description' => 'Short desc for ' . $name,
                    'description' => 'Long description for ' . $name . '...',
                    'price' => (float) $price,
                    'rating_avg' => (float) $ratingAvg,
                ],
                'primary_category' => [
                    'id' => $catModel->id,
                    'name' => $catModel->name,
                    'slug' => $catModel->slug,
                ],
                'breadcrumbs' => $catInfo['breadcrumbs'],
                'categories' => [
                    [
                        'id' => $catModel->id,
                        'name' => $catModel->name,
                    ],
                ],
                'attributes' => $snapshotAttributes,
                'images' => $snapshotImages,
                'sync' => [
                    'version' => 1,
                    'last_synced_at' => $now->toDateTimeString(),
                ],
            ];

            $productsBatch[] = [
                'id' => $productId,
                'sku' => $sku,
                'slug' => $slug,
                'name' => $name,
                'short_description' => 'Short desc for ' . $name,
                'description' => 'Long description for ' . $name . '...',
                'price' => $price,
                'rating_avg' => $ratingAvg,
                'status' => 'active',
                'metadata_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'metadata_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $productIdCounter++;
            $progressBar?->advance();

            if ($i % $batchSize === 0 || $i === $totalProducts) {
                $this->assertUniqueBatchSkus($productsBatch);

                DB::transaction(function () use ($productsBatch, $categoriesBatch, $imagesBatch, $attributesBatch): void {
                    DB::table('products')->insert($productsBatch);
                    DB::table('product_categories')->insert($categoriesBatch);
                    DB::table('product_images')->insert($imagesBatch);
                    DB::table('product_attribute_values')->insert($attributesBatch);
                });

                $productsBatch = [];
                $categoriesBatch = [];
                $imagesBatch = [];
                $attributesBatch = [];
            }
        }

        $progressBar?->finish();
        $this->command?->newLine();
        $this->command?->info("Successfully seeded {$totalProducts} products.");
    }

    /**
     * Pastikan tidak ada SKU ganda dalam satu batch sebelum insert.
     */
    protected function assertUniqueBatchSkus(array $productsBatch): void
    {
        $skus = array_column($productsBatch, 'sku');
        $counts = array_count_values($skus);
        $duplicates = array_filter($counts, fn (int $count): bool => $count > 1);

        if (! empty($duplicates)) {
            throw new RuntimeException(
                'Duplicate SKU detected in current batch: ' . implode(', ', array_keys($duplicates))
            );
        }
    }
}