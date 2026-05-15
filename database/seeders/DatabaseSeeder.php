<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mechanic;
use App\Models\Reminder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $adminRole    = Role::firstOrCreate(['name' => 'admin']);
        $managerRole  = Role::firstOrCreate(['name' => 'manager']);
        $mechanicRole = Role::firstOrCreate(['name' => 'mechanic']);
        $receptRole   = Role::firstOrCreate(['name' => 'receptionist']);

        // Create demo tenant (workshop)
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'motor1000-demo'],
            [
                'name'     => 'Motor1000 - Taller Demo',
                'email'    => 'contato@motor1000demo.com',
                'phone'    => '(11) 99999-0000',
                'address'  => 'Av. Paulista, 1000',
                'city'     => 'São Paulo',
                'state'    => 'SP',
                'timezone' => 'America/Sao_Paulo',
                'currency' => 'ARS',
                'is_active' => true,
                'settings' => ['notifications' => ['email' => true, 'whatsapp' => false]],
            ]
        );

        // Admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@motor1000.test'],
            [
                'tenant_id'         => $tenant->id,
                'name'              => 'Administrador',
                'email_verified_at' => now(),
                'password'          => Hash::make('password'),
                'is_active'         => true,
            ]
        );
        $admin->assignRole($adminRole);

        // Manager user
        $manager = User::updateOrCreate(
            ['email' => 'gerente@motor1000.test'],
            [
                'tenant_id'         => $tenant->id,
                'name'              => 'Gerente',
                'email_verified_at' => now(),
                'password'          => Hash::make('password'),
                'is_active'         => true,
            ]
        );
        $manager->assignRole($managerRole);

        // Mechanics
        $mechanicNames = ['João Silva', 'Carlos Pereira', 'André Souza'];
        foreach ($mechanicNames as $name) {
            Mechanic::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                [
                    'specialty'   => fake()->randomElement(['Motor', 'Elétrica', 'Suspensão', 'Freios']),
                    'phone'       => fake()->phoneNumber(),
                    'is_active'   => true,
                    'hourly_rate' => fake()->randomFloat(2, 80, 200),
                ]
            );
        }

        $mechanics = Mechanic::where('tenant_id', $tenant->id)->get();

        // Customers + Vehicles + WorkOrders
        for ($i = 0; $i < 30; $i++) {
            $customer = Customer::factory()->create([
                'tenant_id' => $tenant->id,
            ]);

            $vehicleCount = fake()->numberBetween(1, 2);
            $vehicles = Vehicle::factory($vehicleCount)->create([
                'tenant_id'   => $tenant->id,
                'customer_id' => $customer->id,
            ]);

            // 1-3 work orders per customer
            $orderCount = fake()->numberBetween(0, 3);
            for ($j = 0; $j < $orderCount; $j++) {
                WorkOrder::factory()->create([
                    'tenant_id'   => $tenant->id,
                    'customer_id' => $customer->id,
                    'vehicle_id'  => $vehicles->random()->id,
                    'mechanic_id' => $mechanics->random()->id,
                ]);
            }

            // Some reminders
            if (fake()->boolean(40)) {
                Reminder::create([
                    'tenant_id'    => $tenant->id,
                    'customer_id'  => $customer->id,
                    'vehicle_id'   => $vehicles->first()->id,
                    'type'         => fake()->randomElement(['oil_change', 'brake_inspection', 'tire_rotation', 'checkup']),
                    'title'        => 'Revisión programada',
                    'trigger_type' => 'date',
                    'due_at'       => fake()->dateTimeBetween('now', '+60 days'),
                    'status'       => 'pending',
                ]);
            }
        }

        // Inactive customers (for testing CRM)
        Customer::factory(10)->inactive()->create(['tenant_id' => $tenant->id]);

        // Demo invoices (at least 5)
        $workOrders = WorkOrder::where('tenant_id', $tenant->id)
            ->with(['customer'])
            ->inRandomOrder()
            ->take(8)
            ->get();

        $invoiceSequence = 1;

        $makeInvoiceNumber = function () use (&$invoiceSequence): string {
            return 'INV-' . str_pad((string) $invoiceSequence++, 5, '0', STR_PAD_LEFT);
        };

        foreach ($workOrders->take(5) as $workOrder) {
            $subtotal = (float) $workOrder->total;
            $tax = round($subtotal * 0.12, 2);
            $discount = fake()->randomFloat(2, 0, min(70, $subtotal));
            $total = max(0, $subtotal + $tax - $discount);
            $status = fake()->randomElement(['pending', 'paid', 'overdue']);

            Invoice::create([
                'tenant_id' => $tenant->id,
                'number' => $makeInvoiceNumber(),
                'customer_id' => $workOrder->customer_id,
                'work_order_id' => $workOrder->id,
                'status' => $status,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => fake()->randomElement(['pix', 'credit_card', 'debit_card', 'cash']),
                'due_at' => now()->addDays(fake()->numberBetween(3, 25)),
                'paid_at' => $status === 'paid' ? now()->subDays(fake()->numberBetween(0, 10)) : null,
                'notes' => fake()->sentence(),
            ]);
        }

        $this->command->info('✅ Demo data seeded exitosamente!');
        $this->command->info('👤 Admin: admin@motor1000.test / password');
        $this->command->info('👤 Manager: gerente@motor1000.test / password');
    }
}
