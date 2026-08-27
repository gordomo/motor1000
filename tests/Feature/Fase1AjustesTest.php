<?php

/**
 * Fase 1 de los pedidos del cliente:
 *  - 4: se retiran los estados Diagnóstico y Esperando piezas del flujo de OTs
 *  - 9: distinguir clientes con trabajo hecho de los que nunca vinieron
 *  - 10: la ficha QR imprimible no muestra el kilometraje
 *  - 11: kilometraje obligatorio al abrir la OT y al presupuestar
 */

use App\Enums\WorkOrderStatus;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\QuoteResource\Pages\CreateQuote;
use App\Filament\Resources\WorkOrderResource\Pages\CreateWorkOrder;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $this->t = Tenant::factory()->create();
    $this->u = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->u->assignRole('admin');
    app()->instance('current.tenant', $this->t);
    $this->actingAs($this->u);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $this->customer->id,
        'mileage'     => 80000,
    ]);
});

// ─── Pedido 4: estados retirados ────────────────────────────────────────────

it('4: el flujo de estados ya no tiene diagnóstico ni esperando piezas', function () {
    $values = array_column(WorkOrderStatus::cases(), 'value');

    expect($values)->toBe(['received', 'repairing', 'completed', 'delivered'])
        ->and(WorkOrderStatus::tryFrom('diagnosis'))->toBeNull()
        ->and(WorkOrderStatus::tryFrom('waiting_parts'))->toBeNull();
});

it('4: la orden avanza recibido → en reparación → completado → entregado', function () {
    expect(WorkOrderStatus::nextStates(WorkOrderStatus::Received))->toBe([WorkOrderStatus::Repairing])
        ->and(WorkOrderStatus::nextStates(WorkOrderStatus::Repairing))->toBe([WorkOrderStatus::Completed])
        ->and(WorkOrderStatus::nextStates(WorkOrderStatus::Completed))->toBe([WorkOrderStatus::Delivered])
        ->and(WorkOrderStatus::nextStates(WorkOrderStatus::Delivered))->toBe([]);
});

it('4: el historial sigue mostrando los estados retirados con su etiqueta', function () {
    // work_order_status_history es auditoría: no se reescribe, se muestra.
    expect(WorkOrderStatus::labelFor('waiting_parts'))->toBe('Esperando piezas')
        ->and(WorkOrderStatus::labelFor('diagnosis'))->toBe('Diagnóstico')
        ->and(WorkOrderStatus::labelFor('repairing'))->toBe('En reparación')
        ->and(WorkOrderStatus::labelFor(null))->toBe('—');
});

// ─── Pedido 11: kilometraje obligatorio ─────────────────────────────────────

it('11: no se puede abrir una orden de trabajo sin kilometraje', function () {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'vehicle_id'  => $this->vehicle->id,
            'priority'    => 'normal',
            'mileage_in'  => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['mileage_in']);
});

it('11: no se puede presupuestar sin kilometraje', function () {
    Livewire::test(CreateQuote::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'vehicle_id'  => $this->vehicle->id,
            'mileage'     => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['mileage']);
});

it('11: el kilometraje de la orden actualiza el del vehículo si es mayor', function () {
    WorkOrder::create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $this->customer->id,
        'vehicle_id'  => $this->vehicle->id,
        'status'      => 'received',
        'complaint'   => 'Ruido en el tren delantero',
        'mileage_in'  => 95000,
    ]);

    expect($this->vehicle->refresh()->mileage)->toBe(95000);
});

it('11: un kilometraje menor no baja el del vehículo', function () {
    // Un KM menor al registrado es un error de tipeo, no un dato nuevo.
    WorkOrder::create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $this->customer->id,
        'vehicle_id'  => $this->vehicle->id,
        'status'      => 'received',
        'complaint'   => 'Cambio de aceite',
        'mileage_in'  => 1000,
    ]);

    expect($this->vehicle->refresh()->mileage)->toBe(80000);
});

it('11: el kilometraje del presupuesto también actualiza el del vehículo', function () {
    Quote::create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $this->customer->id,
        'vehicle_id'  => $this->vehicle->id,
        'mileage'     => 91000,
        'status'      => 'pending',
    ]);

    expect($this->vehicle->refresh()->mileage)->toBe(91000);
});

it('genera la OT desde un presupuesto sin falla declarada', function () {
    // work_orders.complaint es NOT NULL y detected_fault es opcional: esa
    // combinación tiraba un 500 al generar la orden desde el presupuesto.
    $quote = Quote::create([
        'tenant_id'      => $this->t->id,
        'customer_id'    => $this->customer->id,
        'vehicle_id'     => $this->vehicle->id,
        'mileage'        => 85000,
        'status'         => 'accepted',
        'detected_fault' => null,
        'items'          => [
            ['tipo' => 'mano_de_obra', 'descripcion' => 'Service', 'cantidad' => 1, 'precio_unitario' => 50000],
        ],
    ]);

    Livewire::test(\App\Filament\Resources\QuoteResource\Pages\ListQuotes::class)
        ->callTableAction('generate_work_order', $quote, data: [])
        ->assertHasNoTableActionErrors();

    $wo = WorkOrder::where('quote_id', $quote->id)->first();

    expect($wo)->not->toBeNull()
        ->and($wo->complaint)->not->toBeEmpty()
        ->and($wo->mileage_in)->toBe(85000);
});

// ─── Pedido 10: ficha QR sin kilometraje ────────────────────────────────────

it('10: la ficha QR imprimible no muestra el kilometraje', function () {
    $this->get(route('vehicles.qr-card', $this->vehicle))
        ->assertOk()
        ->assertSee($this->vehicle->license_plate)
        ->assertDontSee('80.000 km');
});

it('10: la pantalla del vehículo sí muestra el kilometraje', function () {
    $this->get(route('filament.app.resources.vehicles.view', $this->vehicle))
        ->assertOk()
        ->assertSee('80.000');
});

// ─── Pedido 9: clientes con trabajo hecho ───────────────────────────────────

it('9: separa los clientes con órdenes de los que nunca vinieron', function () {
    $nuevo = Customer::factory()->create(['tenant_id' => $this->t->id, 'name' => 'Nunca Vino']);

    WorkOrder::create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $this->customer->id,
        'vehicle_id'  => $this->vehicle->id,
        'status'      => 'received',
        'complaint'   => 'Service general',
        'mileage_in'  => 80000,
    ]);

    Livewire::test(ListCustomers::class, ['activeTab' => 'con_ordenes'])
        ->assertCanSeeTableRecords([$this->customer])
        ->assertCanNotSeeTableRecords([$nuevo]);

    Livewire::test(ListCustomers::class, ['activeTab' => 'sin_ordenes'])
        ->assertCanSeeTableRecords([$nuevo])
        ->assertCanNotSeeTableRecords([$this->customer]);
});

it('todas las pestañas de clientes filtran de verdad', function () {
    // Regresión: los closures se llamaban fn(Builder $q), y Filament inyecta el
    // builder por el nombre 'query'. Con otro nombre armaba un Builder sin modelo
    // y la pestaña quedaba rota.
    // last_visit_at explícito: el factory lo genera al azar y hacía el test frágil.
    $vip = Customer::factory()->create([
        'tenant_id' => $this->t->id, 'status' => 'vip', 'last_visit_at' => null,
    ]);
    $this->customer->update(['status' => 'active', 'last_visit_at' => now()]);

    Livewire::test(ListCustomers::class, ['activeTab' => 'vip'])
        ->assertCanSeeTableRecords([$vip])
        ->assertCanNotSeeTableRecords([$this->customer]);

    Livewire::test(ListCustomers::class, ['activeTab' => 'active'])
        ->assertCanSeeTableRecords([$this->customer])
        ->assertCanNotSeeTableRecords([$vip]);

    // El vip nunca visitó → cae en "Inactivos" (criterio last_visit_at).
    Livewire::test(ListCustomers::class, ['activeTab' => 'inactive'])
        ->assertCanSeeTableRecords([$vip])
        ->assertCanNotSeeTableRecords([$this->customer]);
});

it('todas las pestañas de órdenes de trabajo filtran de verdad', function () {
    $recibida = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'received',
        'complaint' => 'Recién ingresada', 'mileage_in' => 80000,
    ]);
    $entregada = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'delivered',
        'complaint' => 'Ya entregada', 'mileage_in' => 80000,
    ]);

    Livewire::test(\App\Filament\Resources\WorkOrderResource\Pages\ListWorkOrders::class, ['activeTab' => 'received'])
        ->assertCanSeeTableRecords([$recibida])
        ->assertCanNotSeeTableRecords([$entregada]);

    Livewire::test(\App\Filament\Resources\WorkOrderResource\Pages\ListWorkOrders::class, ['activeTab' => 'delivered'])
        ->assertCanSeeTableRecords([$entregada])
        ->assertCanNotSeeTableRecords([$recibida]);
});
