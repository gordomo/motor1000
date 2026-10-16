<?php

/**
 * Regresión: error 500 al editar la parte financiera de una orden (OT 104 en prod).
 *
 * work_orders.discount y payment_status son NOT NULL con default: el default
 * solo aplica si se omite del UPDATE, no si llega un NULL explícito. Al borrar
 * el descuento (o vaciar el estado de pago) para "volverlo a 0", Filament
 * mandaba NULL y MySQL rechazaba el guardado. Crear la orden ya lo resolvía.
 */

use App\Filament\Resources\WorkOrderResource\Pages\EditWorkOrder;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('admin');
    $this->t = Tenant::factory()->create();
    $this->u = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->u->assignRole('admin');
    app()->instance('current.tenant', $this->t);
    $this->actingAs($this->u);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $vehicle = Vehicle::factory()->create(['tenant_id' => $this->t->id, 'customer_id' => $customer->id]);

    $this->orden = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id, 'status' => 'received', 'complaint' => 'Ruido', 'mileage_in' => 50000,
        'discount' => 500, 'payment_method' => 'cash', 'payment_status' => 'paid',
    ]);
});

it('borrar el descuento y la forma de pago guarda la orden con descuento 0', function () {
    Livewire::test(EditWorkOrder::class, ['record' => $this->orden->getRouteKey()])
        ->fillForm(['discount' => null, 'payment_method' => null, 'payment_status' => 'pending'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->orden->refresh();

    expect((float) $this->orden->discount)->toBe(0.0)
        ->and($this->orden->payment_method)->toBeNull()
        ->and($this->orden->payment_status)->toBe('pending');
});

it('vaciar el estado de pago no rompe el guardado', function () {
    Livewire::test(EditWorkOrder::class, ['record' => $this->orden->getRouteKey()])
        ->fillForm(['payment_status' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->orden->refresh()->payment_status)->toBe('pending');
});
