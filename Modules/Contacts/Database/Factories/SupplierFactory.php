<?php

declare(strict_types=1);

namespace Modules\Contacts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contacts\Models\Supplier;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'doc_type' => 'RUC',
            'doc_number' => $this->faker->numerify('20#########'),
            'name' => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'email' => $this->faker->optional()->companyEmail(),
            'phone' => $this->faker->numerify('9########'),
            'address' => $this->faker->address(),
            'is_active' => true,
        ];
    }
}
