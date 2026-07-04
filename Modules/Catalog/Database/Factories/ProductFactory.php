<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Product;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $cost = $this->faker->randomFloat(2, 1, 200);
        $price = round($cost * $this->faker->randomFloat(2, 1.15, 1.9), 2); // margen 15%-90%

        return [
            'code' => 'PRD-' . str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'sku' => strtoupper($this->faker->bothify('SKU-####??')),
            'name' => ucfirst($this->faker->words(3, true)),
            'description' => $this->faker->sentence(),
            'cost' => $cost,
            'price' => $price,
            'wholesale_price' => round($price * 0.9, 2),
            'stock_min' => $this->faker->numberBetween(5, 20),
            'stock_max' => $this->faker->numberBetween(50, 200),
            'track_stock' => true,
            'is_active' => $this->faker->boolean(92),
        ];
    }
}
