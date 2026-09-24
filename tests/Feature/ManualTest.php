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
        ->assertSee('Corregir un cobro')
        ->assertSee('Borrar un cobro')
        // Y en la guía del administrador, que corrige cualquiera y es el único
        // que puede borrar.
        ->assertSee('Cobros: corregir cualquiera y borrar');
});

// ─── Tipos de usuario: solo para quien da de alta a la gente ────────────────

it('el administrador ve los tipos de usuario y la tabla de permisos', function () {
    comoRol('admin');

    Livewire::test(Manual::class)
        ->assertSee('Tipos de usuario')
        ->assertSee('Quién ve qué');
});

it('el comercial y el mecánico no ven la tabla de permisos', function () {
    // No es secreto, pero para ellos es ruido: les alcanza la guía de su rol.
    foreach (['receptionist', 'mechanic'] as $rol) {
        comoRol($rol);

        Livewire::test(Manual::class)
            ->assertDontSee('Tipos de usuario')
            ->assertDontSee('Quién ve qué');
    }
});

it('el manual ya no menciona al super administrador', function () {
    // No usa este panel ni ve datos del taller: para quien trabaja acá es ruido.
    comoRol('admin');

    Livewire::test(Manual::class)->assertDontSee('Super administrador');

    expect(collect(Livewire::test(Manual::class)->instance()->tiposDeUsuario)->pluck('nombre')->all())
        ->toBe(['Administrador', 'Comercial', 'Mecánico']);
});

it('cada rol trae sus pantallas y sus acciones', function () {
    comoRol('admin');

    $tipos = collect(Livewire::test(Manual::class)->instance()->tiposDeUsuario)->keyBy('nombre');

    foreach (['Administrador', 'Comercial', 'Mecánico'] as $nombre) {
        expect($tipos[$nombre]['pantallas'])->not->toBeEmpty()
            ->and($tipos[$nombre]['puede'])->not->toBeEmpty()
            ->and($tipos[$nombre]['no_puede'])->not->toBeEmpty();
    }

    Livewire::test(Manual::class)
        ->assertSee('Pantallas que ve:')
        ->assertSee('Me pongo a trabajar')
        ->assertSee('Borrar cobros');
});

it('lo que el manual promete coincide con el acceso real de cada rol', function () {
    // El manual afirma qué ve cada uno; esto lo comprueba contra las clases que
    // deciden el acceso, para que no quede desactualizado en silencio.
    comoRol('mechanic');
    expect(\App\Filament\Pages\MechanicBoard::canAccess())->toBeTrue()
        ->and(\App\Filament\Pages\Dashboard::canAccess())->toBeFalse()
        ->and(\App\Filament\Pages\WorkOrdersBoard::canAccess())->toBeFalse()
        ->and(\App\Filament\Pages\AppointmentsCalendar::canAccess())->toBeFalse()
        ->and(\App\Filament\Resources\QuoteResource::canViewAny())->toBeFalse()
        ->and(\App\Filament\Resources\InvoiceResource::canViewAny())->toBeFalse()
        ->and(\App\Filament\Resources\InventoryItemResource::canViewAny())->toBeFalse()
        ->and(\App\Filament\Resources\CustomerResource::canViewAny())->toBeFalse();

    comoRol('receptionist');
    expect(\App\Filament\Resources\QuoteResource::canViewAny())->toBeTrue()
        ->and(\App\Filament\Resources\InvoiceResource::canViewAny())->toBeTrue()
        ->and(\App\Filament\Pages\Dashboard::canAccess())->toBeTrue()
        ->and(\App\Filament\Pages\WorkOrdersBoard::canAccess())->toBeTrue()
        ->and(\App\Filament\Pages\WorkOrderClosuresReport::canAccess())->toBeTrue()
        // Lo que el manual dice que NO puede:
        ->and(\App\Filament\Resources\UserResource::canViewAny())->toBeFalse()
        ->and(\App\Filament\Resources\ChecklistItemResource::canViewAny())->toBeFalse()
        ->and(\App\Filament\Pages\WorkshopSettings::canAccess())->toBeFalse();

    comoRol('admin');
    expect(\App\Filament\Resources\UserResource::canViewAny())->toBeTrue()
        ->and(\App\Filament\Resources\ChecklistItemResource::canViewAny())->toBeTrue()
        ->and(\App\Filament\Pages\WorkshopSettings::canAccess())->toBeTrue()
        ->and(\App\Filament\Pages\Dashboard::canAccess())->toBeTrue();
});

it('el manual se puede imprimir desde cualquier rol', function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        comoRol($rol);

        Livewire::test(Manual::class)->assertActionExists('imprimir');
    }
});
