<?php

namespace Database\Seeders;

use App\Services\Catalog\CategoryHierarchyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function __construct(
        protected CategoryHierarchyService $categoryService,
    ) {}

    public function run(): void
    {
        $categories = [
            'Elektronik' => [
                'Smartphone',
                'Laptop',
                'Aksesoris Elektronik' => ['Kabel & Charger', 'Powerbank', 'Casing'],
            ],
            'Pakaian' => [
                'Pakaian Pria' => ['Kemeja', 'Kaos', 'Celana', 'Jaket'],
                'Pakaian Wanita' => ['Atasan', 'Gaun', 'Rok', 'Pakaian Dalam'],
            ],
            'Rumah Tangga' => [
                'Furniture' => ['Meja', 'Kursi', 'Lemari'],
                'Dapur' => ['Alat Masak', 'Penyimpanan Makanan', 'Peralatan Makan'],
            ],
            'Olahraga' => [
                'Peralatan Lari',
                'Sepeda',
                'Fitness',
            ]
        ];

        foreach ($categories as $rootName => $children) {
            $root = $this->categoryService->createCategory([
                'name' => $rootName,
                'slug' => Str::slug($rootName),
                'is_active' => true,
            ]);

            foreach ($children as $childKey => $childValue) {
                if (is_array($childValue)) {
                    $subParentName = $childKey;
                    $subParent = $this->categoryService->createCategory([
                        'parent_id' => $root->id,
                        'name' => $subParentName,
                        'slug' => Str::slug($subParentName),
                        'is_active' => true,
                    ]);

                    foreach ($childValue as $leafName) {
                        $this->categoryService->createCategory([
                            'parent_id' => $subParent->id,
                            'name' => $leafName,
                            'slug' => Str::slug($leafName),
                            'is_active' => true,
                        ]);
                    }
                } else {
                    $this->categoryService->createCategory([
                        'parent_id' => $root->id,
                        'name' => $childValue,
                        'slug' => Str::slug($childValue),
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
