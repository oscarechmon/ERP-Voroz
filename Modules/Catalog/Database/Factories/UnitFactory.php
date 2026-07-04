<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Unit;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $unit = $this->faker->unique()->randomElement([
            ['Unidad', 'UND'], ['Caja', 'CJA'], ['Paquete', 'PQT'],
            ['Kilogramo', 'KG'], ['Litro', 'LT'], ['Docena', 'DOC'],
        ]);

        return [
            'name' => $unit[0],
            'abbreviation' => $unit[1],
            'is_active' => true,
        ];
    }
}
