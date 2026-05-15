<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => Tenant::factory(),
            'name'             => fake()->name(),
            'email'            => fake()->safeEmail(),
            'phone'            => fake()->phoneNumber(),
            'whatsapp'         => '55' . fake()->numerify('##9########'),
            'document'         => fake()->numerify('###.###.###-##'),
            'document_type'    => 'cpf',
            'birthday'         => fake()->dateTimeBetween('-60 years', '-18 years'),
            'address'          => fake()->streetAddress(),
            'city'             => fake()->city(),
            'state'            => 'SP',
            'zip'              => fake()->numerify('#####-###'),
            'status'           => fake()->randomElement(['active', 'active', 'active', 'vip', 'inactive']),
            'notes'            => fake()->optional()->sentence(),
            'last_visit_at'    => fake()->optional()->dateTimeBetween('-18 months', 'now'),
            'whatsapp_opted_in' => true,
            'email_opted_in'   => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state([
            'status'        => 'inactive',
            'last_visit_at' => fake()->dateTimeBetween('-2 years', '-7 months'),
        ]);
    }

    public function vip(): self
    {
        return $this->state(['status' => 'vip']);
    }
}
