<?php

/**
 * Punto 7: el mecánico solo ve el tablero del taller, elige en qué orden se pone
 * a trabajar y marca qué hizo. Vista pensada para tablet/totem.
 */

use App\Enums\WorkOrderStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\MechanicBoard;
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
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create();
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->mecanicoUser = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->mecanicoUser->assignRole('mechanic');

    $this->juan  = Mechanic::create(['tenant_id' => $this->t->id, 'name' => 'Juan', 'is_active' => true]);
    $this->pedro = Mechanic::create(['tenant_id' => $this->t->id, 'name' => 'Pedro', 'is_active' => true]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'license_plate' => 'AB123CD',
    ]);

    $this->orden = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'received',
        'complaint' => 'Cambiar pastillas', 'mileage_in' => 50000,
        'checklist' => [
            ['id_punto' => 1, 'categoria' => 'Frenos', 'nombre_item' => 'Pastillas delanteras',
             'estado_presupuesto' => 'MAL', 'observacion_previa' => 'Gastadas', 'estado' => null, 'aclaracion' => ''],
        ],
    ]);

    $this->actingAs($this->mecanicoUser);
});

it('el mecánico entra al tablero del taller', function () {
    expect(MechanicBoard::canAccess())->toBeTrue();

    Livewire::test(MechanicBoard::class)
        ->assertOk()
        ->assertSee('AB123CD')
        ->assertSee('Cambiar pastillas');
});

it('el mecánico NO ve el tablero comercial ni los módulos de administración', function () {
    expect(Dashboard::canAccess())->toBeFalse()
        ->and(\App\Filament\Resources\InvoiceResource::canViewAny())->toBeFalse()
        ->and(\App\Filament\Resources\QuoteResource::canViewAny())->toBeFalse()
        ->and(\App\Filament\Resources\InventoryItemResource::canViewAny())->toBeFalse()
        ->and(\App\Filament\Resources\InspectionResource::canViewAny())->toBeFalse();
});

it('el administrador sigue viendo todo', function () {
    $admin = User::factory()->create(['tenant_id' => $this->t->id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(Dashboard::canAccess())->toBeTrue()
        ->and(\App\Filament\Resources\InvoiceResource::canViewAny())->toBeTrue()
        ->and(\App\Filament\Resources\QuoteResource::canViewAny())->toBeTrue()
        // Y también entra al tablero del taller si quiere.
        ->and(MechanicBoard::canAccess())->toBeTrue();
});

it('quien es admin y mecánico a la vez ve todo', function () {
    $ambos = User::factory()->create(['tenant_id' => $this->t->id]);
    $ambos->assignRole('mechanic');
    $ambos->assignRole('admin');
    $this->actingAs($ambos);

    expect(Dashboard::canAccess())->toBeTrue()
        ->and(\App\Filament\Resources\QuoteResource::canViewAny())->toBeTrue();
});

it('el mecánico toma la orden eligiendo su nombre', function () {
    Livewire::test(MechanicBoard::class)
        ->mountAction('empezar', ['order' => $this->orden->id])
        ->setActionData(['mechanic_id' => $this->pedro->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $this->orden->refresh();

    expect($this->orden->status)->toBe(WorkOrderStatus::Repairing)
        ->and($this->orden->mechanic_id)->toBe($this->pedro->id)
        ->and($this->orden->statusHistory()->latest('id')->first()->mechanic_id)->toBe($this->pedro->id);
});

it('el mecánico avisa que no puede empezar y la orden queda marcada', function () {
    Livewire::test(MechanicBoard::class)
        ->mountAction('trabar', ['order' => $this->orden->id])
        ->setActionData(['motivo' => 'Falta el filtro de aceite'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $this->orden->refresh();

    expect($this->orden->isBlocked())->toBeTrue()
        ->and($this->orden->blocked_reason)->toBe('Falta el filtro de aceite')
        // Sigue en Recibido: no se movió de columna.
        ->and($this->orden->status)->toBe(WorkOrderStatus::Received);
});

it('el mecánico cierra la orden marcando los puntos y el trabajo realizado', function () {
    $this->orden->update(['status' => 'repairing', 'mechanic_id' => $this->juan->id]);

    $test = Livewire::test(MechanicBoard::class)
        ->mountAction('completar', ['order' => $this->orden->id]);

    // El repeater usa claves UUID: se parte de los datos que el form ya cargó.
    $data = $test->get('mountedActionsData.0');
    $punto = array_key_first($data['checklist']);
    $data['checklist'][$punto]['estado'] = WorkOrder::PUNTO_HECHO;
    $data['work_performed'] = 'Se cambiaron las pastillas delanteras';

    $test->setActionData($data)
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $this->orden->refresh();

    expect($this->orden->status)->toBe(WorkOrderStatus::Completed)
        ->and($this->orden->work_performed)->toBe('Se cambiaron las pastillas delanteras')
        ->and($this->orden->hasIssues())->toBeFalse();
});

it('el formulario no deja cerrar sin explicar el punto que no se pudo', function () {
    $this->orden->update(['status' => 'repairing']);

    $test = Livewire::test(MechanicBoard::class)
        ->mountAction('completar', ['order' => $this->orden->id]);

    $data = $test->get('mountedActionsData.0');
    $punto = array_key_first($data['checklist']);
    $data['checklist'][$punto]['estado'] = WorkOrder::PUNTO_NO_SE_PUDO;
    $data['checklist'][$punto]['aclaracion'] = '';
    $data['work_performed'] = 'Intenté pero falta el repuesto';

    $test->setActionData($data)
        ->callMountedAction()
        ->assertHasActionErrors();

    // La orden no avanzó y el modal sigue abierto con lo que escribió.
    expect($this->orden->refresh()->status)->toBe(WorkOrderStatus::Repairing);
});

it('el mecánico cierra la orden con un punto sin hacer, explicando por qué', function () {
    $this->orden->update(['status' => 'repairing']);

    $test = Livewire::test(MechanicBoard::class)
        ->mountAction('completar', ['order' => $this->orden->id]);

    $data = $test->get('mountedActionsData.0');
    $punto = array_key_first($data['checklist']);
    $data['checklist'][$punto]['estado'] = WorkOrder::PUNTO_NO_SE_PUDO;
    $data['checklist'][$punto]['aclaracion'] = 'Falta el repuesto, se pidió al proveedor';
    $data['work_performed'] = 'Se revisó todo, falta la pieza';

    $test->setActionData($data)
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $this->orden->refresh();

    expect($this->orden->status)->toBe(WorkOrderStatus::Completed)
        // Queda marcada con novedades para que se note que algo pasó.
        ->and($this->orden->hasIssues())->toBeTrue()
        ->and($this->orden->issuePoints()[0]['aclaracion'])->toBe('Falta el repuesto, se pidió al proveedor');
});

it('el tablero solo muestra órdenes abiertas', function () {
    WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'delivered',
        'complaint' => 'Ya entregada', 'mileage_in' => 50000,
    ]);

    $grupos = Livewire::test(MechanicBoard::class)->instance()->grupos;
    $total = collect($grupos)->sum(fn (array $g): int => $g['items']->count());

    expect($total)->toBe(1);
});
