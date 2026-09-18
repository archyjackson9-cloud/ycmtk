<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Database\Seeders\Concerns\GeneratesPlaceholderImages;
use Illuminate\Database\Seeder;

/**
 * The pilot product catalogue (TOR §6.1, §9 Product entity). This is the
 * seeder the user asked for first and most explicitly ("Provide seeders
 * for initial testing especially products") - it seeds ~46 real
 * Ghanaian farm-produce items across every category so the storefront,
 * cart/checkout, admin catalogue, and low-stock/seasonal edge cases all
 * have something real to click through in a fresh install.
 */
class ProductSeeder extends Seeder
{
    use GeneratesPlaceholderImages;

    /**
     * category slug => list of products.
     * Each product: name, unit, price (GHS), and optional qty/seasonal/
     * featured/low_stock/out_of_stock/short flags.
     */
    protected function catalogue(): array
    {
        return [
            'tomatoes-peppers' => [
                ['name' => 'Fresh Tomatoes (Basket)', 'unit' => 'basket', 'price' => 220.00, 'qty' => 40, 'featured' => true],
                ['name' => 'Fresh Tomatoes (Net)', 'unit' => 'net', 'price' => 45.00, 'qty' => 90],
                ['name' => 'Kpakpo Shito Pepper', 'unit' => 'kg', 'price' => 25.00, 'qty' => 60],
                ['name' => 'Scotch Bonnet Pepper (Mixed)', 'unit' => 'kg', 'price' => 18.00, 'qty' => 75],
            ],
            'leafy-greens-salad' => [
                ['name' => 'Kontomire (Cocoyam Leaves)', 'unit' => 'bunch', 'price' => 8.00, 'qty' => 120],
                ['name' => 'Fresh Cabbage', 'unit' => 'piece', 'price' => 12.00, 'qty' => 80],
                ['name' => 'Ayoyo (Jute Leaves)', 'unit' => 'bunch', 'price' => 6.00, 'qty' => 100],
            ],
            'onions-garlic-alliums' => [
                ['name' => 'Red Onions', 'unit' => 'kg', 'price' => 9.00, 'qty' => 150],
                ['name' => 'Shallots (Local Onion)', 'unit' => 'kg', 'price' => 14.00, 'qty' => 70],
                ['name' => 'Fresh Garlic', 'unit' => 'kg', 'price' => 35.00, 'qty' => 8, 'low_stock' => true],
            ],
            'garden-eggs-okro' => [
                ['name' => 'Garden Eggs (Round)', 'unit' => 'kg', 'price' => 10.00, 'qty' => 90],
                ['name' => 'Garden Eggs (Local/Small)', 'unit' => 'kg', 'price' => 8.00, 'qty' => 85],
                ['name' => 'Fresh Okro', 'unit' => 'kg', 'price' => 12.00, 'qty' => 70],
            ],
            'cassava-products' => [
                ['name' => 'Fresh Cassava Tubers', 'unit' => 'bag', 'price' => 60.00, 'qty' => 45],
                ['name' => 'Gari (Cassava Grits)', 'unit' => 'kg', 'price' => 12.00, 'qty' => 130],
                ['name' => 'Cassava Dough (Agbelima)', 'unit' => 'kg', 'price' => 8.00, 'qty' => 60],
            ],
            'yam-varieties' => [
                ['name' => 'Pona Yam (Large Tuber)', 'unit' => 'tuber', 'price' => 25.00, 'qty' => 100, 'featured' => true],
                ['name' => 'Laboko Yam', 'unit' => 'tuber', 'price' => 20.00, 'qty' => 90],
                ['name' => 'Dente Yam', 'unit' => 'tuber', 'price' => 18.00, 'qty' => 95],
            ],
            'cocoyam-plantain' => [
                ['name' => 'Cocoyam (Taro)', 'unit' => 'kg', 'price' => 7.00, 'qty' => 110],
                ['name' => 'Ripe Plantain', 'unit' => 'bunch', 'price' => 15.00, 'qty' => 100, 'featured' => true],
                ['name' => 'Unripe Plantain (Green)', 'unit' => 'bunch', 'price' => 12.00, 'qty' => 95],
            ],
            'maize-rice' => [
                ['name' => 'White Maize (Dried)', 'unit' => 'kg', 'price' => 6.50, 'qty' => 200],
                ['name' => 'Local Brown Rice', 'unit' => 'kg', 'price' => 14.00, 'qty' => 140],
                ['name' => 'Maize Flour (Corn Dough)', 'unit' => 'kg', 'price' => 9.00, 'qty' => 75],
            ],
            'beans-groundnuts-soya' => [
                ['name' => 'Black-Eyed Beans (Adua)', 'unit' => 'kg', 'price' => 18.00, 'qty' => 85],
                ['name' => 'Roasted Groundnuts', 'unit' => 'kg', 'price' => 22.00, 'qty' => 65],
                ['name' => 'Soybeans', 'unit' => 'kg', 'price' => 16.00, 'qty' => 70],
            ],
            'citrus-fruits' => [
                ['name' => 'Sweet Oranges', 'unit' => 'net', 'price' => 20.00, 'qty' => 100, 'seasonal' => [11, 2]],
                ['name' => 'Fresh Lemons', 'unit' => 'kg', 'price' => 15.00, 'qty' => 60],
            ],
            'tropical-fruits' => [
                ['name' => 'Ripe Pineapple (Smooth Cayenne)', 'unit' => 'piece', 'price' => 12.00, 'qty' => 90, 'featured' => true],
                ['name' => 'Ripe Pawpaw', 'unit' => 'piece', 'price' => 10.00, 'qty' => 80],
                ['name' => 'Watermelon', 'unit' => 'piece', 'price' => 25.00, 'qty' => 50, 'seasonal' => [11, 3]],
            ],
            'herbs-spices' => [
                ['name' => 'Dried Ginger', 'unit' => 'kg', 'price' => 30.00, 'qty' => 40],
                ['name' => 'Fresh Ginger', 'unit' => 'kg', 'price' => 18.00, 'qty' => 55],
                ['name' => 'Dried Chili (Kpakpo)', 'unit' => 'kg', 'price' => 40.00, 'qty' => 6, 'low_stock' => true],
            ],
            'poultry-eggs-fish' => [
                ['name' => 'Fresh Eggs (Crate of 30)', 'unit' => 'crate', 'price' => 55.00, 'qty' => 70, 'featured' => true],
                ['name' => 'Live Local Chicken', 'unit' => 'piece', 'price' => 70.00, 'qty' => 35],
                ['name' => 'Smoked Herrings', 'unit' => 'kg', 'price' => 45.00, 'qty' => 0, 'out_of_stock' => true],
                ['name' => 'Dried Tilapia', 'unit' => 'kg', 'price' => 60.00, 'qty' => 40],
            ],
            'cooking-oils' => [
                ['name' => 'Palm Oil (Zomi)', 'unit' => 'litre', 'price' => 30.00, 'qty' => 90],
                ['name' => 'Groundnut Oil', 'unit' => 'litre', 'price' => 35.00, 'qty' => 60],
            ],
            'packaged-rice-pasta' => [
                ['name' => 'Perfumed Rice (5kg Bag)', 'unit' => 'bag', 'price' => 75.00, 'qty' => 100],
                ['name' => 'Spaghetti (500g Pack)', 'unit' => 'pack', 'price' => 8.00, 'qty' => 150],
            ],
            'canned-preserved-goods' => [
                ['name' => 'Canned Tomato Paste (400g)', 'unit' => 'can', 'price' => 12.00, 'qty' => 120],
                ['name' => 'Tin Fish (Sardines, 155g)', 'unit' => 'can', 'price' => 10.00, 'qty' => 130],
            ],
        ];
    }

    public function run(): void
    {
        $categories = Category::whereNotNull('parent_id')->get()->keyBy('slug');

        foreach ($this->catalogue() as $categorySlug => $products) {
            $category = $categories->get($categorySlug);

            if (! $category) {
                $this->command?->warn("Skipping products for unknown category slug [{$categorySlug}] - run CategorySeeder first.");

                continue;
            }

            foreach ($products as $data) {
                $qty = $data['out_of_stock'] ?? false ? 0 : ($data['qty'] ?? 50);

                /** @var Product $product */
                $product = Product::factory()->create([
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'unit_of_measurement' => $data['unit'],
                    'selling_price' => $data['price'],
                    'wholesale_price' => round($data['price'] * 0.85, 2),
                    'available_quantity' => $data['low_stock'] ?? false ? min($qty, 5) : $qty,
                    'is_featured' => $data['featured'] ?? false,
                    'is_seasonal' => isset($data['seasonal']),
                    'season_start_month' => $data['seasonal'][0] ?? null,
                    'season_end_month' => $data['seasonal'][1] ?? null,
                    'sold_count' => random_int(0, 150),
                    'short_description' => $data['short'] ?? "Farm-fresh {$data['name']}, sourced directly from growers in the Tarkwa pilot zone.",
                ]);

                $path = $this->generatePlaceholderImage($data['name']);

                if ($path) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path' => $path,
                        'is_primary' => true,
                        'sort_order' => 0,
                    ]);
                }
            }
        }

        $this->command?->info('Seeded '.Product::count().' products across '.count($this->catalogue()).' sub-categories.');
    }
}
