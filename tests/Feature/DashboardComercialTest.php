<?php

/**
 * Pedidos 8, 10, 11 y 14: el tablero comercial con las definiciones del cliente.
 *   completada = trabajo terminado listo para cobrar
 *   entregada  = cobro realizado
 *   y filtro de fechas, por defecto el mes actual
 */

use App\Actions\WorkOrder\UpdateWorkOrderStatusAction;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Quote;
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

    $this->admin = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
    ]);

    $this->desde = CarbonImmutable::now()->startOfMonth();
    $this->hasta = CarbonImmutable::now()->endOfMonth();
    $this->service = app(CommercialDashboardService::class);
});

function ot(array $attrs = []): WorkOrder
{
    return WorkOrder::create(array_merge([
        'tenant_id'   => test()->t->id,
        'customer_id' => test()->customer->id,
        'vehicle_id'  => test()->vehicle->id,
        'status'      => 'received',
        'complaint'   => 'Trabajo',
        'mileage_in'  => 50000,
    ], $attrs));
}

function metrics(): array
{
    return test()->service->metrics(test()->t->id, test()->desde, test()->hasta);
}

// ─── Presupuestado ──────────────────────────────────────────────────────────

it('mide lo presupuestado y la tasa de conversión', function () {
    foreach ([
        ['status' => QuoteStatus::Accepted, 'items' => [['tipo' => 'mano_de_obra', 'descripcion' => 'A', 'cantidad' => 1, 'precio_unitario' => 100000]]],
        ['status' => QuoteStatus::Pending,  'items' => [['tipo' => 'mano_de_obra', 'descripcion' => 'B', 'cantidad' => 1, 'precio_unitario' => 50000]]],
        ['status' => QuoteStatus::Rejected, 'items' => [['tipo' => 'mano_de_obra', 'descripcion' => 'C', 'cantidad' => 1, 'precio_unitario' => 30000]]],
        ['status' => QuoteStatus::Accepted, 'items' => [['tipo' => 'mano_de_obra', 'descripcion' => 'D', 'cantidad' => 1, 'precio_unitario' => 20000]]],
    ] as $attrs) {
        Quote::create(array_merge([
            'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id, 'mileage' => 50000,
        ], $attrs));
    }

    $m = metrics()['presupuestado'];

    expect($m['cantidad'])->toBe(4)
        ->and($m['monto'])->toEqual(200000.0)
        ->and($m['monto_aprobado'])->toEqual(120000.0)
        ->and($m['aprobados'])->toBe(2)
        ->and($m['pendientes'])->toBe(1)
        ->and($m['rechazados'])->toBe(1)
        ->and($m['conversion'])->toEqual(50.0);
});

// ─── Por cobrar y cobrado ───────────────────────────────────────────────────

it('la orden completada cuenta como listo para cobrar, no como cobrado', function () {
    $o = ot(['status' => 'repairing', 'work_performed' => 'Listo']);
    $o->items()->create(['type' => 'labor', 'description' => 'Mano de obra', 'quantity' => 1, 'unit_price' => 80000]);

    app(UpdateWorkOrderStatusAction::class)->execute($o->refresh(), WorkOrderStatus::Completed);

    $m = metrics();

    expect($m['por_cobrar']['monto'])->toEqual(80000.0)
        ->and($m['por_cobrar']['cantidad'])->toBe(1)
        ->and($m['cobrado']['monto'])->toEqual(0.0);
});

it('la orden entregada cuenta como cobrada', function () {
    $o = ot(['status' => 'completed', 'work_performed' => 'Listo']);
    $o->items()->create(['type' => 'labor', 'description' => 'Mano de obra', 'quantity' => 1, 'unit_price' => 80000]);

    app(UpdateWorkOrderStatusAction::class)->execute($o->refresh(), WorkOrderStatus::Delivered, options: [
        'payment' => ['amount' => 80000, 'method' => 'efectivo'],
    ]);

    $m = metrics();

    expect($m['cobrado']['monto'])->toEqual(80000.0)
        ->and($m['por_cobrar']['monto'])->toEqual(0.0)
        ->and($m['cobrado']['por_medio'])->toBe(['efectivo' => 80000.0]);
});

it('el adelanto se cuenta en su fecha real, no en la de la entrega', function () {
    $o = ot(['status' => 'completed', 'work_performed' => 'Listo']);
    $o->items()->create(['type' => 'labor', 'description' => 'Mano de obra', 'quantity' => 1, 'unit_price' => 100000]);
    $o->refresh();

    // Adelanto del mes pasado: no debe aparecer en el período actual.
    Payment::create([
        'tenant_id' => $this->t->id, 'work_order_id' => $o->id, 'type' => 'adelanto',
        'amount' => 40000, 'method' => 'transferencia',
        'paid_at' => CarbonImmutable::now()->subMonth()->startOfMonth()->addDay(),
    ]);

    app(UpdateWorkOrderStatusAction::class)->execute($o->refresh(), WorkOrderStatus::Delivered, options: [
        'payment' => ['amount' => 60000, 'method' => 'efectivo'],
    ]);

    // Este mes entraron solo los 60.000 del saldo.
    expect(metrics()['cobrado']['monto'])->toEqual(60000.0);

    // El mes pasado, los 40.000 del adelanto.
    $mesPasado = $this->service->metrics(
        $this->t->id,
        CarbonImmutable::now()->subMonth()->startOfMonth(),
        CarbonImmutable::now()->subMonth()->endOfMonth(),
    );

    expect($mesPasado['cobrado']['monto'])->toEqual(40000.0)
        ->and($mesPasado['cobrado']['adelantos'])->toEqual(40000.0);
});

it('la orden con pago parcial queda con el saldo como listo para cobrar', function () {
    $o = ot(['status' => 'repairing', 'work_performed' => 'Listo']);
    $o->items()->create(['type' => 'labor', 'description' => 'Mano de obra', 'quantity' => 1, 'unit_price' => 100000]);
    $o->refresh();

    Payment::create([
        'tenant_id' => $this->t->id, 'work_order_id' => $o->id, 'type' => 'adelanto',
        'amount' => 30000, 'method' => 'efectivo',
    ]);

    app(UpdateWorkOrderStatusAction::class)->execute($o->refresh(), WorkOrderStatus::Completed);

    expect(metrics()['por_cobrar']['monto'])->toEqual(70000.0);
});

// ─── Desglose por rubro ─────────────────────────────────────────────────────

it('separa mano de obra, repuestos y otros rubros', function () {
    $o = ot(['status' => 'completed', 'work_performed' => 'Listo']);
    $o->items()->create(['type' => 'labor', 'description' => 'Mano de obra', 'quantity' => 1, 'unit_price' => 60000]);
    $o->items()->create(['type' => 'part',  'description' => 'Filtro',        'quantity' => 2, 'unit_price' => 10000]);
    $o->items()->create(['type' => 'other', 'description' => 'Grúa',          'quantity' => 1, 'unit_price' => 15000]);

    app(UpdateWorkOrderStatusAction::class)->execute($o->refresh(), WorkOrderStatus::Delivered, options: [
        'payment' => ['amount' => 95000, 'method' => 'efectivo'],
    ]);

    $r = metrics()['rubros'];

    expect($r['mano_de_obra'])->toEqual(60000.0)
        ->and($r['repuestos'])->toEqual(20000.0)
        ->and($r['otros'])->toEqual(15000.0)
        ->and($r['total'])->toEqual(95000.0);
});

it('el trabajo entregado sin cargo se cuenta aparte y no como plata', function () {
    $o = ot(['status' => 'completed', 'total' => 0, 'work_performed' => 'Revisión sin cargo']);

    app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Delivered);

    $m = metrics();

    expect($m['gratis']['cantidad'])->toBe(1)
        ->and($m['cobrado']['monto'])->toEqual(0.0)
        ->and($m['por_cobrar']['monto'])->toEqual(0.0);
});

// ─── Aislamiento y filtros ──────────────────────────────────────────────────

it('no mezcla los números de otro taller', function () {
    $otro = Tenant::factory()->create();
    $otroCliente = Customer::factory()->create(['tenant_id' => $otro->id]);
    $otroAuto = Vehicle::factory()->create(['tenant_id' => $otro->id, 'customer_id' => $otroCliente->id]);

    $ajena = WorkOrder::create([
        'tenant_id' => $otro->id, 'customer_id' => $otroCliente->id, 'vehicle_id' => $otroAuto->id,
        'status' => 'completed', 'complaint' => 'Ajena', 'mileage_in' => 1000, 'total' => 500000,
    ]);

    Payment::create([
        'tenant_id' => $otro->id, 'work_order_id' => $ajena->id,
        'amount' => 500000, 'method' => 'efectivo',
    ]);

    $m = metrics();

    expect($m['cobrado']['monto'])->toEqual(0.0)
        ->and($m['por_cobrar']['monto'])->toEqual(0.0);
});

it('14: el tablero tiene filtro de fechas y arranca en el mes actual', function () {
    Livewire::test(Dashboard::class)
        ->assertOk()
        // El DatePicker guarda el datetime completo; lo que importa es el día.
        ->assertFormSet(fn (array $state): bool =>
            str_starts_with((string) $state['desde'], now()->startOfMonth()->toDateString())
            && str_starts_with((string) $state['hasta'], now()->endOfMonth()->toDateString()),
            'filtersForm');
});

it('el mecánico no ve los números comerciales', function () {
    $mecanico = User::factory()->create(['tenant_id' => $this->t->id]);
    $mecanico->assignRole('mechanic');
    $this->actingAs($mecanico);

    expect(\App\Filament\Widgets\CommercialOverviewWidget::canView())->toBeFalse()
        ->and(\App\Filament\Widgets\RevenueBreakdownWidget::canView())->toBeFalse();
});
