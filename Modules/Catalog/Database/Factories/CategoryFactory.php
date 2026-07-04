<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Category;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Abarrotes', 'Bebidas', 'Lácteos', 'Limpieza', 'Snacks', 'Panadería',
            'Cuidado personal', 'Licores', 'Congelados', 'Mascotas',
        ]);

        return [
            'name' => $name,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 20),
        ];
    }
}
