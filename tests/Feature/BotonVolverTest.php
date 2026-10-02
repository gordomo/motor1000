<?php

/**
 * Botón "Volver" en crear/editar/ver: el usuario usa mucho la flecha atrás del
 * navegador y eso lo lleva a formularios viejos. El botón (y el "Cancelar" del
 * formulario) van a la pantalla de la que vino, o al listado si no sirve.
 */

use App\Filament\Pages\AppointmentsCalendar;
use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\VehicleResource;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create();
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->u = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->u->assignRole('admin');
    $this->actingAs($this->u);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
    ]);
});

/** href del link cuyo texto es $texto (ej. "Volver"), o null. */
function hrefDe(string $html, string $texto): ?string
{
    preg_match_all('/<a\b[^>]*\bhref="([^"]*)"[^>]*>(.*?)<\/a>/s', $html, $m, PREG_SET_ORDER);

    foreach ($m as [, $href, $contenido]) {
        if (trim(strip_tags($contenido)) === $texto) {
            return html_entity_decode($href);
        }
    }

    return null;
}

it('vuelve al listado con sus filtros si vino de ahí', function () {
    $listado = VehicleResource::getUrl('index') . '?tableSearch=AB1';

    $html = $this->get(VehicleResource::getUrl('edit', ['record' => $this->vehicle]), ['referer' => $listado])
        ->assertOk()->getContent();

    expect(hrefDe($html, 'Volver'))->toBe($listado);
});

it('vuelve al calendario si la cita se abrió desde ahí', function () {
    $cita = Appointment::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'title' => 'Service',
        'scheduled_at' => now()->addDay(), 'duration_minutes' => 60, 'status' => 'scheduled',
    ]);
    $calendario = AppointmentsCalendar::getUrl();

    $html = $this->get(AppointmentResource::getUrl('edit', ['record' => $cita]), ['referer' => $calendario])
        ->assertOk()->getContent();

    expect(hrefDe($html, 'Volver'))->toBe($calendario);
});

it('vuelve a la ficha si vino de la ficha', function () {
    $ficha = VehicleResource::getUrl('view', ['record' => $this->vehicle]);

    $html = $this->get(VehicleResource::getUrl('edit', ['record' => $this->vehicle]), ['referer' => $ficha])
        ->assertOk()->getContent();

    expect(hrefDe($html, 'Volver'))->toBe($ficha);
});

it('nunca vuelve a un formulario de crear o editar: va al listado', function () {
    // Caso típico: crear → Filament redirige a la ficha → "Volver" no debe abrir el alta vacía.
    $html = $this->get(VehicleResource::getUrl('view', ['record' => $this->vehicle]), [
        'referer' => VehicleResource::getUrl('create'),
    ])->assertOk()->getContent();

    expect(hrefDe($html, 'Volver'))->toBe(VehicleResource::getUrl('index'));
});

it('sin pantalla de origen, o si viene de otro sitio, va al listado', function (?string $referer) {
    $html = $this->get(VehicleResource::getUrl('create'), array_filter(['referer' => $referer]))
        ->assertOk()->getContent();

    expect(hrefDe($html, 'Volver'))->toBe(VehicleResource::getUrl('index'));
})->with([null, 'https://www.google.com/panel/vehicles']);

it('el Cancelar del formulario va al mismo lugar que Volver (no usa la flecha atrás)', function () {
    $listado = VehicleResource::getUrl('index') . '?tableSearch=AB1';

    $html = $this->get(VehicleResource::getUrl('edit', ['record' => $this->vehicle]), ['referer' => $listado])
        ->assertOk()->getContent();

    expect(hrefDe($html, 'Cancelar'))->toBe($listado)
        ->and($html)->not->toContain('window.history.back()');
});

it('el calendario carga las traducciones al español de FullCalendar', function () {
    $this->get(AppointmentsCalendar::getUrl())
        ->assertOk()
        ->assertSee('@fullcalendar/core@6.1.15/locales/es.global.min.js', false);
});

it('todas las pantallas de alta del panel tienen Volver', function () {
    $recursos = collect(Filament::getPanel('app')->getResources())
        ->filter(fn (string $r) => $r::hasPage('create') && $r::canCreate());

    expect($recursos)->not->toBeEmpty();

    foreach ($recursos as $recurso) {
        $html = $this->get($recurso::getUrl('create'))->assertOk()->getContent();

        expect(hrefDe($html, 'Volver'))->toBe($recurso::getUrl('index'), $recurso);
    }
});

it('el calendario abre en vista de mes', function () {
    $this->get(AppointmentsCalendar::getUrl())
        ->assertOk()
        ->assertSee("initialView: 'dayGridMonth'", false);
});

it('los turnos llevan clase de color según su estado y las entregas la suya', function () {
    $nueva = fn (string $status) => Appointment::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'title' => 'Service',
        'scheduled_at' => now()->addDay(), 'duration_minutes' => 60, 'status' => $status,
    ]);
    $vigente = $nueva('scheduled');
    $cancelada = $nueva('cancelled');
    $ausente = $nueva('no_show');

    $eventos = collect(\Livewire\Livewire::test(AppointmentsCalendar::class)->get('events'))->keyBy('id');

    expect($eventos[(string) $vigente->id]['classNames'])->toBe(['evento-turno'])
        ->and($eventos[(string) $cancelada->id]['classNames'])->toBe(['evento-cancelado'])
        ->and($eventos[(string) $ausente->id]['classNames'])->toBe(['evento-cancelado']);
});
