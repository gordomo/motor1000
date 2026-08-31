<?php

/**
 * Manual de uso dentro del sistema.
 *
 * Vive en el panel y no en un documento aparte para que no se desincronice: los
 * estados y los pasos salen del enum, no de una copia escrita a mano.
 */

use App\Enums\WorkOrderStatus;
use App\Filament\Pages\Manual;
use App\Filament\Pages\MechanicBoard;
use App\Models\Tenant;
use App\Models\User;
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
});

/** Usuario con el rol pedido, ya autenticado. */
function comoRol(string $rol): User
{
    $u = User::factory()->create(['tenant_id' => test()->t->id]);
    $u->assignRole($rol);
    test()->actingAs($u);

    return $u;
}

it('los tres roles entran al manual', function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        comoRol($rol);

        expect(Manual::canAccess())->toBeTrue();

        Livewire::test(Manual::class)->assertOk();
    }
});

it('el mecánico ve su guía y no la del mostrador', function () {
    comoRol('mechanic');

    Livewire::test(Manual::class)
        ->assertSee('Tu guía: el Tablero del taller')
        ->assertSee('Me pongo a trabajar')
        // La parte de plata no le aparece.
        ->assertDontSee('Entregar y cobrar')
        ->assertDontSee('Presupuestar');
});

it('el comercial ve presupuestar, cobrar y los números', function () {
    comoRol('receptionist');

    Livewire::test(Manual::class)
        ->assertSee('Presupuestar')
        ->assertSee('Entregar y cobrar')
        ->assertSee('Los números')
        // La configuración es solo del administrador.
        ->assertDontSee('Solo vos: el equipo y la configuración');
});

it('el administrador ve además el equipo y la configuración', function () {
    comoRol('admin');

    Livewire::test(Manual::class)
        ->assertSee('Solo vos: el equipo y la configuración')
        ->assertSee('Usuario y mecánico son dos cosas distintas')
        ->assertSee('Puntos de revisión');
});

it('el circuito usa los nombres de estado del sistema, no una copia', function () {
    comoRol('admin');

    Livewire::test(Manual::class)
        ->assertSee(WorkOrderStatus::Received->getLabel())
        ->assertSee(WorkOrderStatus::Repairing->getLabel())
        ->assertSee(WorkOrderStatus::Completed->getLabel())
        ->assertSee(WorkOrderStatus::Delivered->getLabel());

    // Y si mañana cambia una etiqueta, el manual la toma sola.
    $pasos = Livewire::test(Manual::class)->instance()->circuito;

    expect(collect($pasos)->pluck('estado')->all())->toBe([
        WorkOrderStatus::Received->getLabel(),
        WorkOrderStatus::Repairing->getLabel(),
        WorkOrderStatus::Completed->getLabel(),
        WorkOrderStatus::Delivered->getLabel(),
    ]);
});

it('el mecánico llega al manual desde su tablero, que no tiene menú', function () {
    comoRol('mechanic');

    Livewire::test(MechanicBoard::class)
        ->assertOk()
        ->assertSee('¿Cómo se usa?');
});

it('el manual advierte del botón que le avisa al cliente', function () {
    comoRol('mechanic');

    Livewire::test(Manual::class)
        ->assertSee('le avisa al cliente que su auto está listo');
});

it('el mecánico tiene cómo volver al tablero, porque no tiene menú', function () {
    comoRol('mechanic');

    Livewire::test(Manual::class)
        ->assertOk()
        ->assertSee('Volver al tablero');
});

it('el comercial no necesita ese botón: tiene el menú', function () {
    comoRol('receptionist');

    Livewire::test(Manual::class)
        ->assertOk()
        ->assertDontSee('Volver al tablero');
});

it('el manual dice quién puede registrar cobros', function () {
    comoRol('admin');

    Livewire::test(Manual::class)
        ->assertSee('Registrar cobros')
        ->assertSee('Corregir o borrar un cobro')
        // Y en la guía del administrador, que es el único que puede.
        ->assertSee('Corregir cobros');
});
