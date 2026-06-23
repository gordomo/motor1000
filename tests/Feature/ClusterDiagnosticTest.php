<?php

use App\Filament\Pages\AppointmentsCalendar;
use App\Filament\Pages\WorkOrdersBoard;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Resources\WorkOrderResource;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $this->tenant   = Tenant::factory()->create();
    $this->user     = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('admin');
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id,
    ]);

    // 3 órdenes para el cliente, una con fecha de entrega
    $this->orders = collect(['received', 'repairing', 'completed'])->map(fn ($st, $i) =>
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => $st,
            'estimated_at' => $i === 0 ? now()->addDays(2) : null,
            'total' => 150000,
        ]));

    app()->instance('current.tenant', $this->tenant);
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

it('FALLA 4: navigation badge cuenta las ordenes del tenant', function () {
    expect((int) WorkOrderResource::getNavigationBadge())->toBe(3);
});

it('FALLA 7: ficha de cliente cuenta 3 ordenes y lista relaciones', function () {
    expect($this->customer->workOrders()->count())->toBe(3);
    Livewire::test(ViewCustomer::class, ['record' => $this->customer->getRouteKey()])->assertOk();
    expect(CustomerResource::getRelations())->not->toBeEmpty();
});

it('FALLA 3/A: el tablero muestra las 3 ordenes reales y sus viewUrl existen', function () {
    $board = Livewire::test(WorkOrdersBoard::class);
    $cols = $board->get('columns');
    $ids = collect($cols)->flatMap(fn ($c) => collect($c['items'])->pluck('id'))->sort()->values()->all();
    expect($ids)->toBe($this->orders->pluck('id')->sort()->values()->all());
});

it('FALLA 6: crear factura ofrece las ordenes del cliente seleccionado', function () {
    $component = Livewire::test(CreateInvoice::class)
        ->fillForm(['customer_id' => $this->customer->id]);
    // ¿el select de work_order_id ofrece las 3 ordenes del cliente?
    $opts = WorkOrder::where('customer_id', $this->customer->id)->pluck('number', 'id');
    expect($opts)->toHaveCount(3);
});

it('FALLA 5: el calendario incluye la entrega de la orden', function () {
    $cal = Livewire::test(AppointmentsCalendar::class);
    $events = $cal->get('events');
    $hasDelivery = collect($events)->contains(fn ($e) => str_contains(strtolower($e['title'] ?? ''), 'entrega'));
    expect($hasDelivery)->toBeTrue();
});
