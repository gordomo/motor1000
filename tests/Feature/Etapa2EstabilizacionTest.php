<?php

use App\Filament\Pages\WorkOrderClosuresReport;
use App\Filament\Widgets\ExecutiveOverviewWidget;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\DashboardService;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
    $this->tenant = Tenant::factory()->create();
    app()->instance('current.tenant', $this->tenant);
    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Propietaria Reservada',
        'phone' => '3415557890',
        'email' => 'privado@example.test',
        'document' => '30123456',
        'address' => 'Domicilio Privado 123',
    ]);
    $this->vehicle = Vehicle::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

function ordenEstabilizacion(array $attributes = []): WorkOrder
{
    return WorkOrder::factory()->create(array_merge([
        'tenant_id' => test()->tenant->id,
        'customer_id' => test()->customer->id,
        'vehicle_id' => test()->vehicle->id,
        'status' => 'delivered',
        'completed_at' => now(),
        'delivered_at' => now(),
    ], $attributes));
}

it('el QR histórico muestra todos los ingresos y trabajos sin datos personales ni presupuestos', function () {
    for ($i = 1; $i <= 12; $i++) {
        ordenEstabilizacion([
            'work_performed' => "Servicio realizado número {$i}",
            'complaint' => 'Queja privada de Propietaria Reservada',
            'internal_notes' => 'Nota interna reservada',
        ]);
    }
    \App\Models\Quote::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id,
        'type' => 'sin_checklist',
        'status' => 'pending',
        'items' => [['descripcion' => 'Presupuesto privado', 'cantidad' => 1, 'precio_unitario' => 123456.78]],
    ]);

    $response = $this->get(route('vehicle.public', $this->vehicle->public_token))
        ->assertOk()
        ->assertSee($this->vehicle->license_plate)
        ->assertSee('Servicio realizado número 1')
        ->assertSee('Servicio realizado número 12');

    foreach ([$this->customer->name, $this->customer->phone, $this->customer->email,
        $this->customer->document, $this->customer->address, 'Queja privada',
        'Nota interna reservada', 'Presupuesto privado', '123.456,78'] as $private) {
        $response->assertDontSee($private);
    }
});

it('el QR conserva el acceso público aun con sesión de otro taller', function () {
    $other = Tenant::factory()->create();
    $this->actingAs(User::factory()->create(['tenant_id' => $other->id]));
    app()->instance('current.tenant', $other);
    ordenEstabilizacion(['work_performed' => 'Cambio de aceite terminado']);

    $this->get(route('vehicle.public', $this->vehicle->public_token))
        ->assertOk()->assertSee('Cambio de aceite terminado');
    $this->get(route('vehicle.public', 'token-inexistente'))->assertNotFound();
});

it('los ingresos y la serie mensual leen cobros por su fecha real y separan talleres', function () {
    $order = ordenEstabilizacion();
    foreach ([['2026-11-01 00:00:00', 100000], ['2026-11-30 23:59:59', 50000],
        ['2026-10-31 23:59:59', 20000], ['2026-12-01 00:00:00', 70000]] as [$date, $amount]) {
        Payment::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $order->id,
            'amount' => $amount, 'method' => 'efectivo', 'paid_at' => $date]);
    }
    $deleted = Payment::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $order->id,
        'amount' => 99999, 'method' => 'efectivo', 'paid_at' => now()]);
    $deleted->delete();
    $other = Tenant::factory()->create();
    $otherOrder = ordenEstabilizacion(['tenant_id' => $other->id]);
    Payment::create(['tenant_id' => $other->id, 'work_order_id' => $otherOrder->id,
        'amount' => 88888, 'method' => 'efectivo', 'paid_at' => now()]);

    $kpis = app(DashboardService::class)->getKpis($this->tenant->id);
    expect((float) $kpis['monthly_revenue'])->toBe(150000.0)
        ->and($kpis['monthly_revenue_trend'])->toHaveCount(6)
        ->and((float) $kpis['monthly_revenue_trend']['2026-11'])->toBe(150000.0)
        ->and((float) $kpis['monthly_revenue_trend']['2026-10'])->toBe(20000.0)
        ->and((float) $kpis['monthly_revenue_trend']['2026-06'])->toBe(0.0);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($user);
    Livewire::test(ExecutiveOverviewWidget::class)
        ->assertViewHas('currentMonthRevenue', 150000.0)
        ->assertViewHas('revenueVariation', 650.0);
});

it('completadas del mes coincide con el informe incluso con entregas históricas', function () {
    ordenEstabilizacion(['completed_at' => null, 'delivered_at' => now()]);
    ordenEstabilizacion(['completed_at' => now()->subMonth(), 'delivered_at' => now()]);
    ordenEstabilizacion(['status' => 'completed']);
    $deleted = ordenEstabilizacion();
    $deleted->delete();

    $report = new WorkOrderClosuresReport;
    $report->desde = '2026-11-01';
    $report->hasta = '2026-11-30';
    expect(app(DashboardService::class)->getKpis($this->tenant->id)['completed_this_month'])
        ->toBe(2)->toBe($report->getResumenProperty()['total']);
});

it('el informe cuenta días calendario inclusivos sin decimales y normaliza rangos invertidos', function ($from, $to, $days) {
    $report = new WorkOrderClosuresReport;
    $report->desde = $from;
    $report->hasta = $to;
    $summary = $report->getResumenProperty();
    expect($summary['dias'])->toBe($days);
    [$start, $end] = $report->rango();
    expect($start->format('H:i:s'))->toBe('00:00:00')
        ->and($end->format('H:i:s'))->toBe('23:59:59');
})->with([
    ['2026-10-01', '2026-11-01', 32],
    ['2026-11-13', '2026-11-13', 1],
    ['2026-11-01', '2026-10-01', 32],
]);
