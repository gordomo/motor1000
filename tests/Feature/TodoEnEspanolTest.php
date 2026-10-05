<?php

/**
 * El sistema se usa en español: valores guardados en inglés ('pending',
 * 'oil_change'...), mails de Laravel y páginas de error no deben verse en inglés.
 */

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\ReminderResource;
use App\Models\Customer;
use App\Models\Reminder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Etiquetas;
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

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id, 'status' => 'prospect']);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
    ]);
});

it('traduce los valores guardados y deja tal cual los que no conoce', function () {
    expect(Etiquetas::estadoRecordatorio('pending'))->toBe('Pendiente')
        ->and(Etiquetas::tipoRecordatorio('oil_change'))->toBe('Cambio de aceite')
        ->and(Etiquetas::prioridad('urgent'))->toBe('Urgente')
        ->and(Etiquetas::estadoFactura('paid'))->toBe('Pagada')
        ->and(Etiquetas::formaDePago('bank_transfer'))->toBe('Transferencia bancaria')
        ->and(Etiquetas::tipoItem('part'))->toBe('Pieza')
        ->and(Etiquetas::estadoCliente('valor_raro'))->toBe('valor_raro')
        ->and(Etiquetas::prioridad(null))->toBeNull();
});

it('el listado de recordatorios muestra tipo y estado en español', function () {
    Reminder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'type' => 'oil_change',
        'title' => 'Aceite', 'due_at' => now()->addDays(5), 'status' => 'pending',
    ]);

    $html = $this->get(ReminderResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Cambio de aceite')
        ->assertSee('Pendiente')
        ->getContent();

    // En el texto visible no queda el valor crudo (en los value de los filtros sí está, y está bien).
    expect(strip_tags($html))->not->toContain('oil_change');
});

it('el listado de clientes muestra el estado en español', function () {
    $this->get(CustomerResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Prospecto');
});

it('el idioma por defecto es español', function () {
    expect(config('app.locale'))->toBe('es');
});

it('el mail de recuperar contraseña y los errores están en español', function () {
    app()->setLocale('es');

    expect(__('Reset Password Notification'))->toBe('Restablecer contraseña')
        ->and(__('Hello!'))->toBe('¡Hola!')
        ->and(__('passwords.sent'))->not->toContain('emailed')
        ->and(__('auth.failed'))->not->toContain('credentials');

    $this->get('/esta-pagina-no-existe')->assertNotFound()->assertSee('Página no encontrada');
});

it('combustibles de Argentina: Nafta, GNC... y los de Brasil se ven como Sin definir', function () {
    expect(Etiquetas::opcionesCombustible())->toBe([
        'gasoline' => 'Nafta', 'diesel' => 'Diésel', 'gnc' => 'GNC',
        'nafta_gnc' => 'Nafta/GNC', 'electric' => 'Eléctrico', 'hybrid' => 'Híbrido',
    ])
        ->and(Etiquetas::combustible('gasoline'))->toBe('Nafta')
        ->and(Etiquetas::combustible('flex'))->toBe('Sin definir')
        ->and(Etiquetas::combustible('ethanol'))->toBe('Sin definir');
});

it('un vehículo viejo en Flex pide elegir el combustible real al editarlo', function () {
    $this->vehicle->update(['fuel_type' => 'flex']);

    \Livewire\Livewire::test(\App\Filament\Resources\VehicleResource\Pages\EditVehicle::class, ['record' => $this->vehicle->id])
        ->call('save')
        ->assertHasFormErrors(['fuel_type'])
        ->fillForm(['fuel_type' => 'gnc'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->vehicle->fresh()->fuel_type)->toBe('gnc');
});

it('la orden ya no ofrece PIX ni Boleto', function () {
    $this->get(\App\Filament\Resources\WorkOrderResource::getUrl('create'))
        ->assertOk()
        ->assertDontSee('PIX')
        ->assertDontSee('Boleto');
});
