<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $brands = ['Toyota', 'Honda', 'Chevrolet', 'Volkswagen', 'Ford', 'Fiat', 'Hyundai', 'Renault', 'Jeep', 'Nissan'];
        $models = ['Corolla', 'Civic', 'Onix', 'Golf', 'Ka', 'Argo', 'HB20', 'Sandero', 'Compass', 'Kicks'];

        return [
            'tenant_id'       => Tenant::factory(),
            'customer_id'     => Customer::factory(),
            'license_plate'   => strtoupper(fake()->bothify('???-####')),
            'brand'           => fake()->randomElement($brands),
            'model'           => fake()->randomElement($models),
            'year'            => fake()->numberBetween(2010, 2025),
            'color'           => fake()->colorName(),
            'vin'             => strtoupper(fake()->bothify('?????????????????')),
            'mileage'         => fake()->numberBetween(5000, 200000),
            'fuel_type'       => fake()->randomElement(['gasoline', 'flex', 'diesel', 'ethanol']),
            'transmission'    => fake()->randomElement(['manual', 'automatic']),
            'last_service_at' => fake()->optional()->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
