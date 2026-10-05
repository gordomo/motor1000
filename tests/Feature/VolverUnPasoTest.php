<?php

/**
 * El comercial puede volver una orden un paso atrás (definido con el cliente:
 * Ariel, mecánico y comercial, no podía reabrir órdenes). Saltear pasos sigue
 * siendo solo del administrador.
 */

use App\Enums\WorkOrderStatus;
use App\Filament\Pages\WorkOrdersBoard;
use App\Filament\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\CommunicationService;
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

    $c = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $v = Vehicle::factory()->create(['tenant_id' => $this->t->id, 'customer_id' => $c->id]);

    $this->orden = fn (string $status, array $extra = []) => WorkOrder::create(array_merge([
        'tenant_id' => $this->t->id, 'customer_id' => $c->id, 'vehicle_id' => $v->id,
        'status' => $status, 'complaint' => 'x', 'mileage_in' => 1, 'total' => 1000,
    ], $extra));

    $this->usuario = function (string ...$roles): User {
        $u = User::factory()->create(['tenant_id' => $this->t->id]);
        foreach ($roles as $rol) {
            $u->assignRole($rol);
        }
        $this->actingAs($u);

        return $u;
    };
});

it('Ariel (mecánico y comercial) vuelve cualquier orden un paso atrás desde el tablero', function () {
    ($this->usuario)('mechanic', 'receptionist');

    foreach (['repairing' => 'received', 'completed' => 'repairing', 'delivered' => 'completed'] as $de => $a) {
        $o = ($this->orden)($de);
        Livewire::test(WorkOrdersBoard::class)->call('moveOrder', $o->id, $a);

        expect($o->fresh()->status->value)->toBe($a, "$de → $a");
    }
});

it('el comercial no saltea pasos, ni para atrás ni para adelante', function () {
    ($this->usuario)('receptionist');

    $o = ($this->orden)('delivered');
    Livewire::test(WorkOrdersBoard::class)->call('moveOrder', $o->id, 'repairing');
    expect($o->fresh()->status)->toBe(WorkOrderStatus::Delivered);

    $o = ($this->orden)('received');
    Livewire::test(WorkOrdersBoard::class)->call('moveOrder', $o->id, 'completed');
    expect($o->fresh()->status)->toBe(WorkOrderStatus::Received);
});

it('el mecánico solo no vuelve órdenes atrás', function () {
    ($this->usuario)('mechanic');

    expect(\App\Support\WorkOrderTransitions::userCanMove(auth()->user(), WorkOrderStatus::Completed, WorkOrderStatus::Repairing))->toBeFalse();
});

it('el botón Volver un paso guarda el motivo en el historial', function () {
    ($this->usuario)('receptionist');
    $o = ($this->orden)('completed', ['work_performed' => 'hecho']);

    Livewire::test(ListWorkOrders::class)
        ->assertTableActionVisible('volver_paso', $o)
        ->callTableAction('volver_paso', $o, data: ['motivo' => 'Faltó ajustar el freno'])
        ->assertHasNoTableActionErrors();

    expect($o->fresh()->status)->toBe(WorkOrderStatus::Repairing)
        ->and($o->statusHistory()->latest('id')->first()->comment)->toBe('Faltó ajustar el freno');

    // En Recibido no hay a dónde volver.
    $r = ($this->orden)('received');
    Livewire::test(ListWorkOrders::class)->assertTableActionHidden('volver_paso', $r);
});

it('deshacer una entrega no vuelve a avisar al cliente ni cambia la fecha en que se completó', function () {
    ($this->usuario)('receptionist');
    $completada = now()->subDays(10)->startOfMinute();
    // Una orden vieja, entregada antes del checklist: sin trabajo realizado escrito.
    $o = ($this->orden)('delivered', ['completed_at' => $completada, 'delivered_at' => now()->subDays(9), 'work_performed' => null]);

    $this->mock(CommunicationService::class)->shouldNotReceive('notifyVehicleReady');

    Livewire::test(WorkOrdersBoard::class)->call('moveOrder', $o->id, 'completed');

    expect($o->fresh()->status)->toBe(WorkOrderStatus::Completed)
        ->and($o->fresh()->completed_at->equalTo($completada))->toBeTrue();
});
