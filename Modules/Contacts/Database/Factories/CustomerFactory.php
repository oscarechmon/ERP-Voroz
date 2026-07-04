<?php

declare(strict_types=1);

namespace Modules\Contacts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contacts\Models\Customer;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $isCompany = $this->faker->boolean(30);

        return [
            'doc_type' => $isCompany ? 'RUC' : 'DNI',
            'doc_number' => $isCompany ? $this->faker->numerify('20#########') : $this->faker->numerify('########'),
            'name' => $isCompany ? $this->faker->company() : $this->faker->name(),
            'email' => $this->faker->optional()->safeEmail(),
            'phone' => $this->faker->numerify('9########'),
            'address' => $this->faker->address(),
            'is_active' => true,
        ];
    }
}
