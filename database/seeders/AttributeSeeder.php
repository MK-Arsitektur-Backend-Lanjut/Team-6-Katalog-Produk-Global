<?php

namespace Database\Seeders;

use App\Models\Catalog\Category;
use App\Repositories\Contracts\Catalog\AttributeRepositoryInterface;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function __construct(
        protected AttributeRepositoryInterface $attributeRepo,
    ) {}

    public function run(): void
    {
        // 1. Buat Global Attributes (bisa dipakai di semua kategori)
        $brandAttr = $this->attributeRepo->create([
            'code' => 'brand',
            'name' => 'Brand',
            'data_type' => 'text',
            'is_required' => true,
            'is_filterable' => true,
        ]);

        $weightAttr = $this->attributeRepo->create([
            'code' => 'weight',
            'name' => 'Berat',
            'data_type' => 'decimal',
            'unit' => 'gram',
            'is_required' => true,
            'is_filterable' => false,
        ]);

        // 2. Buat Electronics Attributes
        $warrantyAttr = $this->attributeRepo->create([
            'code' => 'warranty_periode',
            'name' => 'Masa Garansi',
            'data_type' => 'select',
            'is_required' => false,
            'is_filterable' => true,
        ]);
        $warrantyAttr->options()->createMany([
            ['label' => 'Tanpa Garansi', 'value' => '0', 'sort_order' => 1],
            ['label' => '6 Bulan', 'value' => '6m', 'sort_order' => 2],
            ['label' => '1 Tahun', 'value' => '1y', 'sort_order' => 3],
            ['label' => '2 Tahun', 'value' => '2y', 'sort_order' => 4],
        ]);

        $ramAttr = $this->attributeRepo->create([
            'code' => 'ram',
            'name' => 'Kapasitas RAM',
            'data_type' => 'select',
            'unit' => 'GB',
            'is_required' => false,
            'is_filterable' => true,
        ]);
        $ramAttr->options()->createMany([
            ['label' => '4 GB', 'value' => '4', 'sort_order' => 1],
            ['label' => '8 GB', 'value' => '8', 'sort_order' => 2],
            ['label' => '16 GB', 'value' => '16', 'sort_order' => 3],
            ['label' => '32 GB', 'value' => '32', 'sort_order' => 4],
        ]);

        // 3. Buat Fashion Attributes
        $sizeAttr = $this->attributeRepo->create([
            'code' => 'size',
            'name' => 'Ukuran',
            'data_type' => 'select',
            'is_required' => true,
            'is_filterable' => true,
        ]);
        $sizeAttr->options()->createMany([
            ['label' => 'S', 'value' => 's', 'sort_order' => 1],
            ['label' => 'M', 'value' => 'm', 'sort_order' => 2],
            ['label' => 'L', 'value' => 'l', 'sort_order' => 3],
            ['label' => 'XL', 'value' => 'xl', 'sort_order' => 4],
        ]);

        $colorAttr = $this->attributeRepo->create([
            'code' => 'color',
            'name' => 'Warna',
            'data_type' => 'select',
            'is_required' => true,
            'is_filterable' => true,
        ]);
        $colorAttr->options()->createMany([
            ['label' => 'Hitam', 'value' => 'black', 'sort_order' => 1],
            ['label' => 'Putih', 'value' => 'white', 'sort_order' => 2],
            ['label' => 'Merah', 'value' => 'red', 'sort_order' => 3],
            ['label' => 'Biru', 'value' => 'blue', 'sort_order' => 4],
        ]);

        // 4. Sync ke Kategori
        $rootCategories = Category::where('parent_id', null)->get();
        foreach ($rootCategories as $cat) {
            // Semua root punya brand dan weight
            $this->attributeRepo->syncCategoryAttributes($cat->id, [
                $brandAttr->id => ['is_required' => true, 'sort_order' => 1],
                $weightAttr->id => ['is_required' => true, 'sort_order' => 2],
            ]);

            if ($cat->name === 'Elektronik') {
                $this->attributeRepo->syncCategoryAttributes($cat->id, [
                    $brandAttr->id => ['is_required' => true, 'sort_order' => 1],
                    $weightAttr->id => ['is_required' => true, 'sort_order' => 2],
                    $warrantyAttr->id => ['is_required' => false, 'sort_order' => 3],
                ]);

                // Smartphone / Laptop punya RAM
                $smartphones = Category::where('slug', 'smartphone')->orWhere('slug', 'laptop')->get();
                foreach ($smartphones as $gadgetCat) {
                    $this->attributeRepo->syncCategoryAttributes($gadgetCat->id, [
                        $ramAttr->id => ['is_required' => true, 'sort_order' => 4],
                    ]);
                }
            } elseif ($cat->name === 'Pakaian') {
                $this->attributeRepo->syncCategoryAttributes($cat->id, [
                    $brandAttr->id => ['is_required' => true, 'sort_order' => 1],
                    $weightAttr->id => ['is_required' => true, 'sort_order' => 2],
                    $sizeAttr->id => ['is_required' => true, 'sort_order' => 3],
                    $colorAttr->id => ['is_required' => true, 'sort_order' => 4],
                ]);
            }
        }
    }
}
