<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Tenant;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // El throttle del turnero acumula entre tests (mismo IP); lo desactivamos
    // para los tests funcionales (no testeamos el límite acá).
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

    $this->branch = Tenant::factory()->create([
        'name'                   => '341 Boxes Centro',
        'brand_slug'             => '341boxes',
        'accepts_online_booking' => true,
        'public_api_key'         => 'test-key-341',
        'is_active'              => true,
        'timezone'               => 'America/Argentina/Buenos_Aires',
    ]);

    // Próximo martes (siempre futuro y día abierto por defecto), 10:00 dentro de horario.
    $this->fecha = Carbon::now($this->branch->timezone)->next(Carbon::TUESDAY)->format('Y-m-d');
    $this->hora  = '10:00';

    $this->payload = [
        'branch_id'  => $this->branch->id,
        'servicios'  => ['Frenos', 'Lubricantes (aceite)'],
        'fecha'      => $this->fecha,
        'hora'       => $this->hora,
        'nombre'     => 'Juan Pérez',
        'whatsapp'   => '+54 9 341 555 1234',
        'email'      => 'juan@example.com',
        'vehiculo'   => 'Ford Ka 2015',
        'comentario' => 'ruido al frenar',
        '_hp'        => '',
    ];
});

function apiKey(): array
{
    return ['X-Api-Key' => 'test-key-341'];
}

it('lista las sucursales de la marca con API key', function () {
    $res = $this->getJson('/api/public/branches', apiKey());
    $res->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('branches.0.nombre', '341 Boxes Centro');
});

it('rechaza sin API key', function () {
    $this->getJson('/api/public/branches')->assertStatus(401);
});

it('rechaza con API key inválida', function () {
    $this->getJson('/api/public/branches', ['X-Api-Key' => 'no-existe'])->assertStatus(401);
});

it('crea una cita real desde el turnero y el cliente', function () {
    $res = $this->postJson('/api/public/appointments', $this->payload, apiKey());

    $res->assertCreated()->assertJsonPath('ok', true);

    $appt = Appointment::withoutGlobalScopes()->where('tenant_id', $this->branch->id)->first();
    expect($appt)->not->toBeNull()
        ->and($appt->status)->toBe('scheduled')
        ->and($appt->source)->toBe('web_turnero')
        ->and($appt->title)->toContain('Frenos');

    $customer = Customer::withoutGlobalScopes()->where('tenant_id', $this->branch->id)->first();
    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Juan Pérez');
});

it('reutiliza el cliente existente por whatsapp', function () {
    $this->postJson('/api/public/appointments', $this->payload, apiKey())->assertCreated();
    $otro = array_merge($this->payload, ['hora' => '11:00', 'nombre' => 'Juan P.']);
    $this->postJson('/api/public/appointments', $otro, apiKey())->assertCreated();

    expect(Customer::withoutGlobalScopes()->where('tenant_id', $this->branch->id)->count())->toBe(1);
});

it('rechaza un slot ya reservado', function () {
    $this->postJson('/api/public/appointments', $this->payload, apiKey())->assertCreated();
    $this->postJson('/api/public/appointments', $this->payload, apiKey())
        ->assertStatus(422)
        ->assertJsonValidationErrors('hora');
});

it('rechaza fecha pasada', function () {
    $pasado = array_merge($this->payload, [
        'fecha' => Carbon::now($this->branch->timezone)->subDays(1)->format('Y-m-d'),
    ]);
    $this->postJson('/api/public/appointments', $pasado, apiKey())
        ->assertStatus(422)
        ->assertJsonValidationErrors('hora');
});

it('rechaza si el honeypot viene lleno', function () {
    $spam = array_merge($this->payload, ['_hp' => 'soy un bot']);
    $this->postJson('/api/public/appointments', $spam, apiKey())
        ->assertStatus(422)
        ->assertJsonValidationErrors('_hp');
});

it('rechaza una sucursal que no es de la marca', function () {
    $otra = Tenant::factory()->create(['brand_slug' => 'otra-marca', 'accepts_online_booking' => true, 'is_active' => true]);
    $payload = array_merge($this->payload, ['branch_id' => $otra->id]);
    $this->postJson('/api/public/appointments', $payload, apiKey())
        ->assertStatus(422)
        ->assertJsonValidationErrors('branch_id');
});

it('devuelve disponibilidad de un día con el horario libre', function () {
    $res = $this->getJson("/api/public/availability?branch_id={$this->branch->id}&date={$this->fecha}", apiKey());
    $res->assertOk()->assertJsonPath('ok', true);
    $slots = collect($res->json('slots'));
    expect($slots)->not->toBeEmpty();
    expect($slots->firstWhere('time', '10:00')['available'])->toBeTrue();
});

it('marca el horario como no disponible cuando se llena la capacidad', function () {
    // capacidad 1 (default): tras reservar 10:00, deja de estar disponible.
    $this->postJson('/api/public/appointments', $this->payload, apiKey())->assertCreated();

    $res = $this->getJson("/api/public/availability?branch_id={$this->branch->id}&date={$this->fecha}", apiKey());
    $slots = collect($res->json('slots'));
    expect($slots->firstWhere('time', '10:00')['available'])->toBeFalse();
});

it('respeta una capacidad mayor a 1 por franja', function () {
    $this->branch->update(['booking_settings' => ['slot_capacity' => 2]]);

    $this->postJson('/api/public/appointments', $this->payload, apiKey())->assertCreated();
    // segundo turno en el mismo slot: permitido (capacidad 2)
    $otro = array_merge($this->payload, ['nombre' => 'Otro', 'whatsapp' => '+54 9 341 999 0000']);
    $this->postJson('/api/public/appointments', $otro, apiKey())->assertCreated();
    // tercero: rechazado
    $tercero = array_merge($this->payload, ['nombre' => 'Tercero', 'whatsapp' => '+54 9 341 111 2222']);
    $this->postJson('/api/public/appointments', $tercero, apiKey())
        ->assertStatus(422)
        ->assertJsonValidationErrors('hora');
});

it('rechaza un horario fuera del horario de atención', function () {
    $fuera = array_merge($this->payload, ['hora' => '20:00']); // cierra 17:00
    $this->postJson('/api/public/appointments', $fuera, apiKey())
        ->assertStatus(422)
        ->assertJsonValidationErrors('hora');
});

it('requiere email válido', function () {
    $bad = array_merge($this->payload, ['email' => 'no-es-email']);
    $this->postJson('/api/public/appointments', $bad, apiKey())
        ->assertStatus(422)->assertJsonValidationErrors('email');
});

it('encola el mail de confirmación al crear el turno', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $this->postJson('/api/public/appointments', $this->payload, apiKey())->assertCreated();
    \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\AppointmentConfirmationMail::class);
});

it('el link firmado confirma el turno (scheduled -> confirmed)', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $res = $this->postJson('/api/public/appointments', $this->payload, apiKey())->assertCreated();
    $id = $res->json('id');
    $appt = \App\Models\Appointment::withoutGlobalScopes()->find($id);
    expect($appt->status)->toBe('scheduled')->and($appt->client_confirmed_at)->toBeNull();

    $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('public.appointments.confirm', now()->addDay(), ['appointment' => $id]);
    $this->get($url)->assertOk()->assertSee('Turno confirmado');

    $appt->refresh();
    expect($appt->status)->toBe('confirmed')->and($appt->client_confirmed_at)->not->toBeNull();
});

it('rechaza el confirm sin firma válida', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $res = $this->postJson('/api/public/appointments', $this->payload, apiKey())->assertCreated();
    $this->get('/turno/' . $res->json('id') . '/confirmar')->assertStatus(403);
});
