<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Module 2: fake hardware products for tests.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 1, 200);

        return [
            'sku' => 'HW-'.strtoupper(fake()->unique()->bothify('???-####')),
            'name' => ucfirst(fake()->words(3, true)),
            'category' => fake()->randomElement(Product::CATEGORIES),
            'description' => null,
            'unit_type' => fake()->randomElement(Product::UNIT_TYPES),
            'lhdn_class_code' => Product::LHDN_CLASS_CODE,
            'supplier_cost_price' => $cost,
            'retail_selling_price' => round($cost * 1.3, 2),
            'stock_quantity' => 100,
            'low_stock_threshold' => 10,
            'status' => Product::STATUS_ACTIVE,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 5,
            'low_stock_threshold' => 10,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Product::STATUS_ARCHIVED,
        ]);
    }
}
