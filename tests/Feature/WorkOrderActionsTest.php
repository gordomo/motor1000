<?php

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;

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
    app()->instance('current.tenant', $this->tenant);
    $this->actingAs($this->user);
});

// Falla #E: el botón "Avanzar" debe mover la orden al siguiente estado y sellar timestamps.
it('advances a work order to the next status', function () {
    $order = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id,
        'status' => WorkOrderStatus::Repairing,
        'completed_at' => null,
    ]);

    $next = WorkOrderStatus::nextStates($order->status)[0];
    app(\App\Actions\WorkOrder\UpdateWorkOrderStatusAction::class)->execute($order, $next);

    $order->refresh();
    expect($order->status)->toBe(WorkOrderStatus::Completed)
        ->and($order->completed_at)->not->toBeNull();
});

// Falla #E: el PDF de la orden debe generarse sin error.
it('renders the work order PDF', function () {
    $order = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id,
        'status' => WorkOrderStatus::Completed,
    ]);

    $response = $this->get(route('work-orders.pdf', $order));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
});
