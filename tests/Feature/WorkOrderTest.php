<?php

use App\Actions\WorkOrder\CreateWorkOrderAction;
use App\DTOs\CreateWorkOrderDTO;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\WorkOrderResource\Pages\CreateWorkOrder;
use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->tenant   = Tenant::factory()->create();
    $this->user     = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id'   => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    app()->instance('current.tenant', $this->tenant);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

it('creates a work order with correct defaults', function () {
    $dto = new CreateWorkOrderDTO(
        tenantId:   $this->tenant->id,
        customerId: $this->customer->id,
        vehicleId:  $this->vehicle->id,
        mechanicId: null,
        complaint:  'Engine noise',
    );

    $order = app(CreateWorkOrderAction::class)->execute($dto);

    expect($order)->toBeInstanceOf(WorkOrder::class)
        ->and($order->status)->toBe(WorkOrderStatus::Received)
        ->and($order->number)->toStartWith('WO-')
        ->and($order->tenant_id)->toBe($this->tenant->id);
});

it('generates sequential work order numbers per tenant', function () {
    $dto = new CreateWorkOrderDTO(
        tenantId:   $this->tenant->id,
        customerId: $this->customer->id,
        vehicleId:  $this->vehicle->id,
        mechanicId: null,
        complaint:  'Test',
    );

    $action = app(CreateWorkOrderAction::class);
    $first  = $action->execute($dto);
    $second = $action->execute($dto);

    expect($first->number)->not->toBe($second->number);
});

it('updates vehicle and customer last visit on work order creation', function () {
    $dto = new CreateWorkOrderDTO(
        tenantId:   $this->tenant->id,
        customerId: $this->customer->id,
        vehicleId:  $this->vehicle->id,
        mechanicId: null,
        complaint:  'Test',
    );

    app(CreateWorkOrderAction::class)->execute($dto);

    expect($this->customer->fresh()->last_visit_at)->not->toBeNull()
        ->and($this->vehicle->fresh()->last_service_at)->not->toBeNull();
});

it('creates a work order from the filament form', function () {
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'mechanic_id' => null,
            'priority' => 'normal',
            'mileage_in' => 45000,
            'complaint' => 'Ruido al frenar',
            'diagnosis' => 'Revisar pastillas delanteras',
            'discount' => null,
            'payment_status' => null,
            'items' => [
                [
                    'type' => 'labor',
                    'description' => 'Diagnóstico inicial',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = WorkOrder::query()
        ->where('customer_id', $this->customer->id)
        ->where('vehicle_id', $this->vehicle->id)
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->tenant_id)->toBe($this->tenant->id)
        ->and($order->status)->toBe(WorkOrderStatus::Received)
        ->and($order->items)->toHaveCount(1)
        ->and((float) $order->total)->toBe(100.0)
        ->and($this->customer->fresh()->last_visit_at)->not->toBeNull()
        ->and($this->vehicle->fresh()->last_service_at)->not->toBeNull();
});
