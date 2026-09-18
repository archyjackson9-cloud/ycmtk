<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 *
 * Sensible defaults for every Product column; ProductSeeder overrides
 * name/category/price/unit/quantity per item so the pilot catalogue reads
 * as real Ghanaian farm produce rather than generic lorem-ipsum products.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'sku' => 'CYM-'.strtoupper(Str::random(8)),
            'description' => fake()->paragraphs(2, true),
            'short_description' => fake()->sentence(12),
            'unit_of_measurement' => fake()->randomElement(['kg', 'bag', 'basket', 'bunch', 'piece', 'crate']),
            'selling_price' => fake()->randomFloat(2, 5, 120),
            'wholesale_price' => null,
            'min_order_quantity' => 1,
            'available_quantity' => fake()->numberBetween(20, 200),
            'reserved_quantity' => 0,
            'low_stock_threshold' => 10,
            'production_location' => 'Tarkwa, Western Region, Ghana',
            'packaging_type' => fake()->randomElement(['Net bag', 'Basket', 'Loose', 'Crate', 'Sack', 'Box']),
            'weight' => null,
            'is_seasonal' => false,
            'season_start_month' => null,
            'season_end_month' => null,
            'is_active' => true,
            'is_featured' => false,
            'sold_count' => 0,
            'views_count' => fake()->numberBetween(0, 300),
            'average_rating' => 0,
            'ratings_count' => 0,
            'specifications' => null,
        ];
    }

    public function seasonal(int $startMonth, int $endMonth): static
    {
        return $this->state(fn () => [
            'is_seasonal' => true,
            'season_start_month' => $startMonth,
            'season_end_month' => $endMonth,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_quantity' => fake()->numberBetween(1, $attributes['low_stock_threshold'] ?? 10),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['available_quantity' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
