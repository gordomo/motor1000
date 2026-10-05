<?php

/**
 * Capacidad del taller: cuántos autos se atienden a la vez (Mi Taller →
 * "Autos a la vez"). Un turno ocupa lugar todo lo que dura. La agenda muestra
 * los lugares que quedan por horario y no deja reservar uno lleno; el turnero
 * de la web usa la misma regla.
 */

use App\Filament\Pages\AppointmentsCalendar;
use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\AppointmentResource\Pages\CreateAppointment;
use App\Filament\Resources\AppointmentResource\Pages\EditAppointment;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Booking\SlotAvailability;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 08:00'));
    \Spatie\Permission\Models\Role::findOrCreate('receptionist');

    $this->t = Tenant::factory()->create(['booking_settings' => ['slot_capacity' => 3, 'slot_minutes' => 30]]);
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $u = User::factory()->create(['tenant_id' => $this->t->id]);
    $u->assignRole('receptionist');
    $this->actingAs($u);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->turno = fn (string $cuando, int $min = 60, string $status = 'scheduled') => Appointment::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'title' => 'Service',
        'scheduled_at' => $cuando, 'duration_minutes' => $min, 'status' => $status,
    ]);
});

it('cuenta los autos que están en el taller, no solo los que entran a esa hora', function () {
    ($this->turno)('2026-10-07 09:00', 120);   // 09:00 a 11:00
    ($this->turno)('2026-10-07 10:00', 30);
    ($this->turno)('2026-10-07 10:00', 30, 'cancelled');   // no ocupa

    $s = app(SlotAvailability::class);
    expect($s->ocupacionEntre($this->t, Carbon::parse('2026-10-07 09:00'), 30))->toBe(1)
        ->and($s->ocupacionEntre($this->t, Carbon::parse('2026-10-07 10:00'), 30))->toBe(2)
        ->and($s->ocupacionEntre($this->t, Carbon::parse('2026-10-07 11:00'), 30))->toBe(0);
});

it('la lista de horas dice cuántos lugares quedan y marca los llenos', function () {
    foreach (range(1, 3) as $_) {
        ($this->turno)('2026-10-07 09:00', 60);   // 3 autos de 09:00 a 10:00
    }
    ($this->turno)('2026-10-07 10:00', 30);

    $libres = AppointmentResource::lugaresLibres('2026-10-07', 60, null);

    expect($libres['09:00'])->toBe(0)
        ->and($libres['09:30'])->toBe(0)    // 09:30-10:30 pisa a los de las 9
        ->and($libres['10:00'])->toBe(2)
        ->and($libres['11:00'])->toBe(3);
});

it('no deja crear un turno en un horario lleno', function () {
    foreach (range(1, 3) as $_) {
        ($this->turno)('2026-10-07 09:00', 60);
    }

    Livewire::test(CreateAppointment::class)
        ->fillForm(['customer_id' => $this->customer->id, 'fecha' => '2026-10-07', 'hora' => '09:00', 'duration_minutes' => 60])
        ->call('create')
        ->assertHasFormErrors(['hora']);

    Livewire::test(CreateAppointment::class)
        ->fillForm(['customer_id' => $this->customer->id, 'fecha' => '2026-10-07', 'hora' => '10:00', 'duration_minutes' => 60])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Appointment::count())->toBe(4);
});

it('editar un turno que ya está en un horario lleno no lo traba', function () {
    $turnos = collect(range(1, 3))->map(fn () => ($this->turno)('2026-10-07 09:00', 60));

    Livewire::test(EditAppointment::class, ['record' => $turnos->first()->id])
        ->fillForm(['title' => 'Service completo'])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('arrastrar en el calendario a un horario lleno no lo mueve', function () {
    foreach (range(1, 3) as $_) {
        ($this->turno)('2026-10-07 09:00', 60);
    }
    $otro = ($this->turno)('2026-10-07 14:00', 60);

    expect(fn () => Livewire::test(AppointmentsCalendar::class)
        ->call('updateAppointmentSchedule', $otro->id, '2026-10-07T09:00:00', '2026-10-07T10:00:00'))
        ->toThrow(RuntimeException::class);

    expect($otro->fresh()->scheduled_at->format('H:i'))->toBe('14:00');
});

it('el turnero de la web usa la misma regla: un turno largo ocupa las franjas siguientes', function () {
    $this->t->update(['booking_settings' => ['slot_capacity' => 1, 'slot_minutes' => 30]]);
    ($this->turno)('2026-10-07 09:00', 120);   // martes, 09:00 a 11:00

    $slots = collect(app(SlotAvailability::class)->forDate($this->t->fresh(), Carbon::parse('2026-10-07')))
        ->pluck('available', 'time');

    expect($slots['09:00'])->toBeFalse()
        ->and($slots['10:30'])->toBeFalse()
        ->and($slots['11:00'])->toBeTrue();
});
