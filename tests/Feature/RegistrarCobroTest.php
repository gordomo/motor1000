<?php

/**
 * Registrar el cobro de órdenes ya entregadas.
 *
 * Las 48 entregas anteriores a los cobros no tienen forma de pago registrada, así
 * que no cuentan como cobradas. Con esto se carga la forma de pago y la plata
 * entra a los números, imputada a la fecha en que realmente entró.
 */

use App\Filament\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\CommercialDashboardService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create();
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->comercial = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->comercial->assignRole('receptionist');
    $this->actingAs($this->comercial);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
    ]);

    // Una entrega vieja, como las que ya están en producción: sin cobro registrado.
    $this->entregadaVieja = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'delivered',
        'complaint' => 'Service', 'mileage_in' => 50000, 'total' => 500,
        'delivered_at' => CarbonImmutable::now()->subMonths(2)->startOfMonth()->addDays(3),
    ]);
});

function medir(CarbonImmutable $desde, CarbonImmutable $hasta): array
{
    return app(CommercialDashboardService::class)->metrics(test()->t->id, $desde, $hasta);
}

it('la entrega vieja sin cobro figura como pendiente, no como cobrada', function () {
    $m = medir(CarbonImmutable::now()->subYear(), CarbonImmutable::now());

    expect($m['cobrado']['monto'])->toEqual(0.0)
        ->and($m['por_cobrar']['monto'])->toEqual(500.0)
        ->and($m['por_cobrar']['cantidad_entregado'])->toBe(1);
});

it('al registrar la forma de pago, la orden pasa a contar como cobrada', function () {
    Livewire::test(ListWorkOrders::class)
        ->callTableAction('registrar_cobro', $this->entregadaVieja, data: [
            'amount'  => 500,
            'method'  => 'efectivo',
            'paid_at' => $this->entregadaVieja->delivered_at->toDateTimeString(),
        ])
        ->assertHasNoTableActionErrors();

    $this->entregadaVieja->refresh();

    expect($this->entregadaVieja->totalPaid())->toEqual(500.0)
        ->and($this->entregadaVieja->balance())->toEqual(0.0)
        ->and($this->entregadaVieja->payment_status)->toBe('paid');

    $m = medir(CarbonImmutable::now()->subYear(), CarbonImmutable::now());

    expect($m['cobrado']['monto'])->toEqual(500.0)
        // Discriminado por forma de pago, como pidió el cliente.
        ->and($m['cobrado']['por_medio'])->toBe(['efectivo' => 500.0])
        ->and($m['por_cobrar']['monto'])->toEqual(0.0);
});

it('el cobro cuenta en el mes en que entró la plata, no en el mes actual', function () {
    Livewire::test(ListWorkOrders::class)
        ->callTableAction('registrar_cobro', $this->entregadaVieja, data: [
            'amount'  => 500,
            'method'  => 'transferencia',
            'paid_at' => $this->entregadaVieja->delivered_at->toDateTimeString(),
        ])
        ->assertHasNoTableActionErrors();

    // En el mes de la entrega sí aparece.
    $mesEntrega = medir(
        CarbonImmutable::parse($this->entregadaVieja->delivered_at)->startOfMonth(),
        CarbonImmutable::parse($this->entregadaVieja->delivered_at)->endOfMonth(),
    );

    // En el mes actual no, porque la plata no entró ahora.
    $mesActual = medir(CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth());

    expect($mesEntrega['cobrado']['monto'])->toEqual(500.0)
        ->and($mesActual['cobrado']['monto'])->toEqual(0.0);
});

it('un cobro parcial deja el saldo pendiente', function () {
    Livewire::test(ListWorkOrders::class)
        ->callTableAction('registrar_cobro', $this->entregadaVieja, data: [
            'amount'  => 200,
            'method'  => 'efectivo',
            'paid_at' => $this->entregadaVieja->delivered_at->toDateTimeString(),
        ])
        ->assertHasNoTableActionErrors();

    $this->entregadaVieja->refresh();

    expect($this->entregadaVieja->payment_status)->toBe('partial')
        ->and($this->entregadaVieja->balance())->toEqual(300.0);

    $m = medir(CarbonImmutable::now()->subYear(), CarbonImmutable::now());

    expect($m['cobrado']['monto'])->toEqual(200.0)
        ->and($m['por_cobrar']['monto'])->toEqual(300.0);
});

it('borrar un cobro devuelve la orden a pendiente de cobro', function () {
    $cobro = Payment::create([
        'tenant_id' => $this->t->id, 'work_order_id' => $this->entregadaVieja->id,
        'amount' => 500, 'method' => 'efectivo',
        'paid_at' => $this->entregadaVieja->delivered_at,
    ]);

    expect($this->entregadaVieja->refresh()->payment_status)->toBe('paid');

    $cobro->delete();

    $this->entregadaVieja->refresh();

    expect($this->entregadaVieja->payment_status)->toBe('pending')
        ->and($this->entregadaVieja->balance())->toEqual(500.0)
        // Y deja de contar como cobrado.
        ->and(medir(CarbonImmutable::now()->subYear(), CarbonImmutable::now())['cobrado']['monto'])->toEqual(0.0);
});

it('registra el cobro de varias órdenes de una vez, con la fecha de cada entrega', function () {
    $otra = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'delivered',
        'complaint' => 'Otra', 'mileage_in' => 50000, 'total' => 1500,
        'delivered_at' => CarbonImmutable::now()->subMonth()->startOfMonth()->addDays(5),
    ]);

    // Una sin cargo, que debe saltearse.
    $gratis = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'delivered',
        'complaint' => 'Revisión sin cargo', 'mileage_in' => 50000, 'total' => 0,
        'delivered_at' => CarbonImmutable::now()->subMonth(),
    ]);

    Livewire::test(ListWorkOrders::class)
        ->callTableBulkAction('registrar_cobros', [$this->entregadaVieja, $otra, $gratis], data: [
            'method' => 'efectivo',
            'fecha'  => 'entrega',
        ])
        ->assertHasNoTableBulkActionErrors();

    expect(Payment::count())->toBe(2)
        ->and($gratis->refresh()->payments()->count())->toBe(0)
        // Cada cobro quedó imputado al mes de su entrega.
        ->and($this->entregadaVieja->refresh()->payments()->first()->paid_at->month)
        ->toBe($this->entregadaVieja->delivered_at->month)
        ->and($otra->refresh()->payments()->first()->paid_at->month)
        ->toBe($otra->delivered_at->month);
});

it('el mecánico no puede registrar cobros', function () {
    $mecanico = User::factory()->create(['tenant_id' => $this->t->id]);
    $mecanico->assignRole('mechanic');
    $this->actingAs($mecanico);

    Livewire::test(ListWorkOrders::class)
        ->assertTableActionHidden('registrar_cobro', $this->entregadaVieja);
});
