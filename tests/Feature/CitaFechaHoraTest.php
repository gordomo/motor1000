<?php

/**
 * La cita se agenda con Fecha (calendario) y Hora (lista con buscador) por
 * separado: el selector nativo de fecha/hora cambiaba con la ruedita del mouse
 * y se agendaban turnos a otra hora sin querer.
 */

use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\AppointmentResource\Pages\CreateAppointment;
use App\Filament\Resources\AppointmentResource\Pages\EditAppointment;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::findOrCreate('receptionist');
    $this->t = Tenant::factory()->create();
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $u = User::factory()->create(['tenant_id' => $this->t->id]);
    $u->assignRole('receptionist');
    $this->actingAs($u);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
});

it('guarda la fecha y la hora elegidas', function () {
    Livewire::test(CreateAppointment::class)
        ->fillForm(['customer_id' => $this->customer->id, 'fecha' => '2026-10-20', 'hora' => '14:30'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Appointment::sole()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-10-20 14:30');
});

it('la hora es obligatoria', function () {
    Livewire::test(CreateAppointment::class)
        ->fillForm(['customer_id' => $this->customer->id, 'fecha' => '2026-10-20', 'hora' => null])
        ->call('create')
        ->assertHasFormErrors(['hora' => 'required']);
});

it('desde el calendario llega con la fecha y la hora del clic', function () {
    Livewire::withQueryParams(['scheduled_at' => '2026-10-21 09:30:00'])
        ->test(CreateAppointment::class)
        ->assertFormSet(['fecha' => '2026-10-21', 'hora' => '09:30']);
});

it('al editar muestra la fecha y la hora del turno, aunque esté fuera de la grilla', function () {
    $cita = Appointment::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'title' => 'Service',
        'scheduled_at' => '2026-10-22 10:15:00', 'duration_minutes' => 60, 'status' => 'scheduled',
    ]);

    Livewire::test(EditAppointment::class, ['record' => $cita->id])
        ->assertFormSet(['fecha' => '2026-10-22', 'hora' => '10:15'])
        ->fillForm(['hora' => '11:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cita->fresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-10-22 11:00');
});

it('los horarios van cada lo que diga Mi Taller, de 07:00 a 21:00', function () {
    $opciones = AppointmentResource::opcionesDeHora();
    expect(array_key_first($opciones))->toBe('07:00')
        ->and(array_key_last($opciones))->toBe('21:00')
        ->and($opciones)->toHaveKey('14:30')
        ->and(count($opciones))->toBe(29);

    $this->t->update(['booking_settings' => ['slot_minutes' => 15]]);
    app()->instance('current.tenant', $this->t->fresh());
    expect(AppointmentResource::opcionesDeHora())->toHaveKey('14:45');
});

it('la página ya no usa el selector nativo de fecha y hora', function () {
    $html = $this->get(AppointmentResource::getUrl('create'))->assertOk()->getContent();

    // Ningún <input> de fecha/hora nativo (la palabra sí aparece en el CSS/JS que bloquea la ruedita).
    expect(preg_match('/<input[^>]*type="(datetime-local|date|time)"/', $html))->toBe(0);
});
