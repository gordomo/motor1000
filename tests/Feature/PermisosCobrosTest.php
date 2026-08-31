<?php

/**
 * Registrar un cobro es trabajo del mostrador; corregirlo o borrarlo es mover
 * plata ya contada en los números, así que queda solo en el administrador.
 */

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Filament\Facades\Filament;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create();
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->admin = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->admin->assignRole('admin');
    $this->comercial = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->comercial->assignRole('receptionist');
    $this->mecanico = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->mecanico->assignRole('mechanic');

    $customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $vehicle  = Vehicle::factory()->create(['tenant_id' => $this->t->id, 'customer_id' => $customer->id]);

    $orden = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
        'status' => 'delivered', 'complaint' => 'Service', 'mileage_in' => 50000, 'total' => 500,
        'delivered_at' => now()->subMonth(),
    ]);

    $this->cobro = Payment::create([
        'tenant_id' => $this->t->id, 'work_order_id' => $orden->id,
        'amount' => 500, 'method' => 'efectivo', 'paid_at' => now()->subMonth(),
    ]);
});

it('el comercial puede registrar cobros', function () {
    expect($this->comercial->can('create', Payment::class))->toBeTrue()
        ->and($this->comercial->can('viewAny', Payment::class))->toBeTrue();
});

it('el comercial puede corregir el cobro que registró él', function () {
    // Equivocarse al cargar es normal: si puso "efectivo" y era transferencia,
    // lo arregla él sin depender del dueño.
    $propio = Payment::create([
        'tenant_id' => $this->t->id, 'work_order_id' => $this->cobro->work_order_id,
        'amount' => 100, 'method' => 'efectivo', 'paid_at' => now(),
        'user_id' => $this->comercial->id,
    ]);

    expect($this->comercial->can('update', $propio))->toBeTrue();
});

it('el comercial NO puede corregir el cobro de otra persona', function () {
    $deOtro = Payment::create([
        'tenant_id' => $this->t->id, 'work_order_id' => $this->cobro->work_order_id,
        'amount' => 100, 'method' => 'efectivo', 'paid_at' => now(),
        'user_id' => $this->admin->id,
    ]);

    expect($this->comercial->can('update', $deOtro))->toBeFalse();
});

it('el comercial nunca puede borrar un cobro, ni el propio', function () {
    $propio = Payment::create([
        'tenant_id' => $this->t->id, 'work_order_id' => $this->cobro->work_order_id,
        'amount' => 100, 'method' => 'efectivo', 'paid_at' => now(),
        'user_id' => $this->comercial->id,
    ]);

    expect($this->comercial->can('delete', $propio))->toBeFalse();
});

it('los cobros sin autor registrado solo los corrige el administrador', function () {
    // Los cargados antes de esto no tienen autor, así que no hay forma de saber
    // de quién eran.
    expect($this->cobro->user_id)->toBeNull()
        ->and($this->comercial->can('update', $this->cobro))->toBeFalse()
        ->and($this->admin->can('update', $this->cobro))->toBeTrue();
});

it('el administrador puede corregir y borrar', function () {
    expect($this->admin->can('update', $this->cobro))->toBeTrue()
        ->and($this->admin->can('delete', $this->cobro))->toBeTrue();
});

it('el mecánico no toca cobros ni los ve', function () {
    expect($this->mecanico->can('viewAny', Payment::class))->toBeFalse()
        ->and($this->mecanico->can('create', Payment::class))->toBeFalse()
        ->and($this->mecanico->can('update', $this->cobro))->toBeFalse()
        ->and($this->mecanico->can('delete', $this->cobro))->toBeFalse();
});

it('nadie toca los cobros de otro taller', function () {
    $otroAdmin = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $otroAdmin->assignRole('admin');

    expect($otroAdmin->can('update', $this->cobro))->toBeFalse()
        ->and($otroAdmin->can('delete', $this->cobro))->toBeFalse()
        ->and($otroAdmin->can('view', $this->cobro))->toBeFalse();
});

it('corregir el cobro recalcula el estado de pago de la orden', function () {
    $orden = $this->cobro->workOrder;

    expect($orden->refresh()->payment_status)->toBe('paid');

    $this->actingAs($this->admin);
    $this->cobro->update(['amount' => 200]);

    expect($orden->refresh()->payment_status)->toBe('partial')
        ->and($orden->balance())->toEqual(300.0);
});
