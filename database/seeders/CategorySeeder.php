<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Category -> sub-category tree for the pilot catalogue (TOR §6.1 Product
 * Catalogue & Multi-Category Management; Expansion §6.1 "nested category ->
 * sub-category -> produce type"). Products (the "produce type" leaf) are
 * seeded onto the sub-categories in ProductSeeder.
 */
class CategorySeeder extends Seeder
{
    /**
     * Root category name => [description, [sub-category names]].
     * A root with an empty sub-category list carries products directly.
     */
    protected function tree(): array
    {
        return [
            'Fresh Vegetables' => [
                'Locally grown vegetables harvested fresh for the pilot zone.',
                ['Tomatoes & Peppers', 'Leafy Greens & Salad', 'Onions, Garlic & Alliums', 'Garden Eggs & Okro'],
            ],
            'Tubers & Root Crops' => [
                'Cassava, yam and cocoyam straight from farm stores near Tarkwa.',
                ['Cassava Products', 'Yam Varieties', 'Cocoyam & Plantain'],
            ],
            'Grains & Legumes' => [
                'Staple grains, beans and groundnuts.',
                ['Maize & Rice', 'Beans, Groundnuts & Soya'],
            ],
            'Fresh Fruits' => [
                'Seasonal fruit from the Western Region.',
                ['Citrus Fruits', 'Tropical Fruits'],
            ],
            'Herbs & Spices' => [
                'Fresh and dried herbs, roots and chillies.',
                [],
            ],
            'Poultry, Eggs & Fish' => [
                'Live poultry, fresh eggs and smoked/dried fish.',
                [],
            ],
            'Household Provisions' => [
                'Everyday pantry staples that complement the fresh catalogue.',
                ['Cooking Oils', 'Packaged Rice & Pasta', 'Canned & Preserved Goods'],
            ],
        ];
    }

    public function run(): void
    {
        $sortOrder = 0;

        foreach ($this->tree() as $rootName => [$description, $children]) {
            $root = Category::updateOrCreate(
                ['slug' => Str::slug($rootName)],
                [
                    'parent_id' => null,
                    'name' => $rootName,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]
            );

            $childSort = 0;
            foreach ($children as $childName) {
                Category::updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'parent_id' => $root->id,
                        'name' => $childName,
                        'description' => null,
                        'is_active' => true,
                        'sort_order' => $childSort++,
                    ]
                );
            }
        }
    }
}
