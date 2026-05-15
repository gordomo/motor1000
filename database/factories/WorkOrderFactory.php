<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['received', 'diagnosis', 'repairing', 'completed', 'delivered']);

        return [
            'tenant_id'    => Tenant::factory(),
            'customer_id'  => Customer::factory(),
            'vehicle_id'   => Vehicle::factory(),
            'mechanic_id'  => null,
            'status'       => $status,
            'priority'     => fake()->randomElement(['low', 'normal', 'normal', 'high']),
            'complaint'    => fake()->sentence(),
            'diagnosis'    => fake()->optional()->paragraph(),
            'mileage_in'   => fake()->numberBetween(10000, 200000),
            'labor_cost'   => fake()->randomFloat(2, 100, 1500),
            'parts_cost'   => fake()->randomFloat(2, 0, 3000),
            'discount'     => 0,
            'total'        => fn(array $attrs) => $attrs['labor_cost'] + $attrs['parts_cost'],
            'payment_status' => 'pending',
            'estimated_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
            'completed_at' => in_array($status, ['completed', 'delivered'])
                ? fake()->dateTimeBetween('-30 days', 'now')
                : null,
        ];
    }
}
