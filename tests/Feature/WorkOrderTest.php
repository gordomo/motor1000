<?php

use App\Actions\WorkOrder\CreateWorkOrderAction;
use App\DTOs\CreateWorkOrderDTO;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;

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
