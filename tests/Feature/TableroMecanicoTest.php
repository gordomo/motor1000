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

it('el mecánico marca un punto como hecho con un toque, sin abrir nada', function () {
    $this->orden->update(['status' => 'repairing']);

    Livewire::test(MechanicBoard::class)
        ->call('marcarHecho', $this->orden->id, 0);

    $checklist = $this->orden->refresh()->checklist;

    expect($checklist[0]['estado'])->toBe(WorkOrder::PUNTO_HECHO);
});

it('el mecánico puede desmarcar un punto que tocó de más', function () {
    $this->orden->update(['status' => 'repairing']);

    Livewire::test(MechanicBoard::class)
        ->call('marcarHecho', $this->orden->id, 0)
        ->call('desmarcarPunto', $this->orden->id, 0);

    expect($this->orden->refresh()->checklist[0]['estado'])->toBeNull();
});

it('marcar "no se pudo" pide el motivo y lo guarda en el punto', function () {
    $this->orden->update(['status' => 'repairing']);

    Livewire::test(MechanicBoard::class)
        ->mountAction('noSePudo', ['order' => $this->orden->id, 'indice' => 0])
        ->setActionData(['aclaracion' => 'Falta el repuesto'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $punto = $this->orden->refresh()->checklist[0];

    expect($punto['estado'])->toBe(WorkOrder::PUNTO_NO_SE_PUDO)
        ->and($punto['aclaracion'])->toBe('Falta el repuesto');
});

it('el cierre solo pide el trabajo realizado, porque los puntos ya están marcados', function () {
    $this->orden->update(['status' => 'repairing', 'mechanic_id' => $this->juan->id]);

    Livewire::test(MechanicBoard::class)
        ->call('marcarHecho', $this->orden->id, 0)
        ->mountAction('completar', ['order' => $this->orden->id])
        ->setActionData(['work_performed' => 'Se cambiaron las pastillas delanteras'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $this->orden->refresh();

    expect($this->orden->status)->toBe(WorkOrderStatus::Completed)
        ->and($this->orden->work_performed)->toBe('Se cambiaron las pastillas delanteras');
});

it('con puntos sin marcar el cierre no avanza y avisa qué falta', function () {
    $this->orden->update(['status' => 'repairing']);

    Livewire::test(MechanicBoard::class)
        ->mountAction('completar', ['order' => $this->orden->id])
        ->setActionData(['work_performed' => 'Algo hice'])
        ->callMountedAction();

    // Sigue en reparación, pero el texto quedó guardado.
    expect($this->orden->refresh()->status)->toBe(WorkOrderStatus::Repairing)
        ->and($this->orden->work_performed)->toBe('Algo hice');
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

// ─── Claridad de la tarjeta ─────────────────────────────────────────────────

it('los grupos usan los mismos nombres de estado que el resto del sistema', function () {
    Livewire::test(MechanicBoard::class)
        ->assertSee(WorkOrderStatus::Repairing->getLabel())
        ->assertSee(WorkOrderStatus::Received->getLabel())
        // Los nombres inventados no van más.
        ->assertDontSee('En el taller ahora')
        ->assertDontSee('Para empezar');
});

it('la tarjeta dice qué hacer con ella', function () {
    Livewire::test(MechanicBoard::class)
        ->assertSee('Tocá "Me pongo a trabajar" para tomar este auto.');

    $this->orden->update(['status' => 'repairing']);

    Livewire::test(MechanicBoard::class)
        ->assertSee('Marcá cada punto')
        ->assertSee('Falta marcar 1 punto para poder cerrar');
});

it('la tarjeta muestra los puntos a trabajar y cuántos van marcados', function () {
    $this->orden->update([
        'status'    => 'repairing',
        'checklist' => [
            ['id_punto' => 1, 'nombre_item' => 'Pastillas delanteras', 'estado_presupuesto' => 'MAL', 'estado' => WorkOrder::PUNTO_HECHO, 'aclaracion' => ''],
            ['id_punto' => 2, 'nombre_item' => 'Presión neumáticos',   'estado_presupuesto' => 'REGULAR', 'estado' => null, 'aclaracion' => ''],
        ],
    ]);

    Livewire::test(MechanicBoard::class)
        ->assertSee('Pastillas delanteras')
        ->assertSee('Presión neumáticos')
        ->assertSee('1/2')
        ->assertSee('Falta marcar 1 punto para poder cerrar');
});

it('las órdenes con el checklist viejo muestran el nombre de sus puntos', function () {
    // Las 53 órdenes que existen en prod guardaron item/done/note, con los 4 puntos
    // fijos que traía el formulario. La vista los dibujaba en blanco porque buscaba
    // los campos nuevos.
    $this->orden->update([
        'status'    => 'repairing',
        'checklist' => [
            ['item' => 'Luces y señales',       'done' => true,  'note' => null],
            ['item' => 'Nivel de fluidos',      'done' => false, 'note' => 'revisar'],
            ['item' => 'Frenos',                'done' => false, 'note' => null],
            ['item' => 'Presión de neumáticos', 'done' => false, 'note' => null],
        ],
    ]);

    $puntos = $this->orden->workChecklist();

    expect($puntos)->toHaveCount(4)
        ->and($puntos[0]['nombre_item'])->toBe('Luces y señales')
        // El tilde viejo de "revisado" se lee como hecho.
        ->and($puntos[0]['estado'])->toBe(WorkOrder::PUNTO_HECHO)
        ->and($puntos[1]['estado'])->toBeNull();

    Livewire::test(MechanicBoard::class)
        ->assertSee('Luces y señales')
        ->assertSee('Presión de neumáticos')
        ->assertSee('1/4')
        ->assertSee('Checklist cargado en la orden');
});

it('marcar un punto convierte el checklist viejo al formato nuevo', function () {
    $this->orden->update([
        'status'    => 'repairing',
        'checklist' => [['item' => 'Frenos', 'done' => false, 'note' => null]],
    ]);

    Livewire::test(MechanicBoard::class)
        ->call('marcarHecho', $this->orden->id, 0);

    $guardado = $this->orden->refresh()->checklist;

    expect($guardado[0])->toHaveKey('nombre_item')
        ->and($guardado[0]['nombre_item'])->toBe('Frenos')
        ->and($guardado[0]['estado'])->toBe(WorkOrder::PUNTO_HECHO);
});

it('dice que los puntos vienen del presupuesto cuando la orden nació de uno', function () {
    $quote = \App\Models\Quote::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'mileage' => 50000, 'status' => 'accepted',
    ]);

    $this->orden->update(['status' => 'repairing', 'quote_id' => $quote->id]);

    expect($this->orden->refresh()->checklistOrigen())->toBe('Del presupuesto ' . $quote->code);
});

it('avisa cuando la orden no tiene puntos cargados', function () {
    $this->orden->update(['status' => 'repairing', 'checklist' => null]);

    Livewire::test(MechanicBoard::class)
        ->assertSee('Esta orden no tiene puntos cargados.');
});

it('una orden en curso sin mecánico se puede tomar sin cambiar de estado', function () {
    $this->orden->update(['status' => 'repairing', 'mechanic_id' => null]);

    Livewire::test(MechanicBoard::class)
        ->mountAction('hacerseCargo', ['order' => $this->orden->id])
        ->setActionData(['mechanic_id' => $this->juan->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $this->orden->refresh();

    expect($this->orden->mechanic_id)->toBe($this->juan->id)
        ->and($this->orden->status)->toBe(WorkOrderStatus::Repairing);
});

it('el mecánico no ve el calendario de citas ni el listado de órdenes en el menú', function () {
    expect(\App\Filament\Pages\AppointmentsCalendar::canAccess())->toBeFalse()
        ->and(\App\Filament\Resources\WorkOrderResource::shouldRegisterNavigation())->toBeFalse();
});

it('recepción sí ve el calendario y el listado', function () {
    $comercial = User::factory()->create(['tenant_id' => $this->t->id]);
    $comercial->assignRole('receptionist');
    $this->actingAs($comercial);

    expect(\App\Filament\Pages\AppointmentsCalendar::canAccess())->toBeTrue()
        ->and(\App\Filament\Resources\WorkOrderResource::shouldRegisterNavigation())->toBeTrue();
});
