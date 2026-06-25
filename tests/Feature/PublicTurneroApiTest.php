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

    // fecha/hora futuras válidas
    $this->fecha = Carbon::now($this->branch->timezone)->addDays(2)->format('Y-m-d');
    $this->hora  = '10:00';

    $this->payload = [
        'branch_id'  => $this->branch->id,
        'servicios'  => ['Frenos', 'Lubricantes (aceite)'],
        'fecha'      => $this->fecha,
        'hora'       => $this->hora,
        'nombre'     => 'Juan Pérez',
        'whatsapp'   => '+54 9 341 555 1234',
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
