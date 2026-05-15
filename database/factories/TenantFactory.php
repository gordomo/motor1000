<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company() . ' Oficina';
        return [
            'name'     => $name,
            'slug'     => Str::slug($name),
            'email'    => fake()->companyEmail(),
            'phone'    => fake()->phoneNumber(),
            'address'  => fake()->streetAddress(),
            'city'     => fake()->city(),
            'state'    => 'SP',
            'timezone' => 'America/Sao_Paulo',
            'currency' => 'ARS',
            'is_active' => true,
            'settings' => ['notifications' => ['email' => true, 'whatsapp' => true]],
        ];
    }
}
