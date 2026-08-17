<?php

/**
 * Regresiones del CRM de vehículos (errores 500 reportados por el cliente).
 *
 * vehicles.mileage es NOT NULL DEFAULT 0: el default de la columna solo aplica
 * si se omite del INSERT, no si llega un NULL explícito. Con el campo de KM
 * vacío, Filament mandaba NULL y MySQL rechazaba el guardado.
 */

use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\VehiclesRelationManager;
use App\Filament\Resources\VehicleResource\Pages\CreateVehicle;
use App\Filament\Resources\VehicleResource\Pages\EditVehicle;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $this->t = Tenant::factory()->create();
    $this->u = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->u->assignRole('admin');
    app()->instance('current.tenant', $this->t);
    $this->actingAs($this->u);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

it('crea un vehículo desde la ficha del cliente con el kilometraje vacío', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->t->id]);

    Livewire::test(VehiclesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass'   => EditCustomer::class,
    ])
        ->callTableAction('create', data: [
            'license_plate' => 'AB123CD',
            'brand'         => 'Toyota',
            'model'         => 'Corolla',
            'year'          => 2020,
            'mileage'       => null,   // ← el caso que rompía con un 500
        ])
        ->assertHasNoTableActionErrors();

    expect(Vehicle::where('customer_id', $customer->id)->first())
        ->not->toBeNull()
        ->mileage->toBe(0);
});

it('crea un vehículo desde su propio alta con el kilometraje vacío', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->t->id]);

    Livewire::test(CreateVehicle::class)
        ->fillForm([
            'customer_id'   => $customer->id,
            'license_plate' => 'XY987ZW',
            'brand'         => 'Fiat',
            'model'         => 'Argo',
            'year'          => 2021,
            'mileage'       => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Vehicle::where('license_plate', 'XY987ZW')->first()->mileage)->toBe(0);
});

it('edita un vehículo borrando el kilometraje sin romper', function () {
    $vehicle = Vehicle::factory()->create([
        'tenant_id'   => $this->t->id,
        'customer_id' => Customer::factory()->create(['tenant_id' => $this->t->id])->id,
        'mileage'     => 50000,
    ]);

    Livewire::test(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm(['mileage' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vehicle->refresh()->mileage)->toBe(0);
});

it('no descarta el vehículo en silencio si falta un campo obligatorio en la carga rápida', function () {
    // Antes: con el año vacío el cliente se creaba y el vehículo se perdía sin aviso.
    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'name'                  => 'Juan Pérez',
            'phone'                 => '3412632104',
            'status'                => 'active',
            'vehicle.license_plate' => 'AA111BB',
            'vehicle.brand'         => 'Honda',
            'vehicle.model'         => 'Civic',
            'vehicle.year'          => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['vehicle.year']);

    expect(Customer::where('name', 'Juan Pérez')->exists())->toBeFalse();
});

it('crea cliente y vehículo juntos cuando están los cuatro campos', function () {
    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'name'                  => 'Ana Gómez',
            'phone'                 => '3412632105',
            'status'                => 'active',
            'vehicle.license_plate' => 'CC222DD',
            'vehicle.brand'         => 'Ford',
            'vehicle.model'         => 'Ka',
            'vehicle.year'          => 2019,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $customer = Customer::where('name', 'Ana Gómez')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->vehicles()->where('license_plate', 'CC222DD')->exists())->toBeTrue();
});

it('crea el cliente solo, sin tocar la sección de vehículo', function () {
    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'name'   => 'Solo Cliente',
            'phone'  => '3412632106',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Customer::where('name', 'Solo Cliente')->exists())->toBeTrue();
});

it('la página pública del vehículo abre aunque el cliente esté borrado', function () {
    // Customer y Vehicle usan SoftDeletes: al borrar el cliente el vehículo sobrevive
    // y $vehicle->customer queda null → las vistas explotaban con un 500.
    $customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $vehicle  = Vehicle::factory()->create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $customer->id,
    ]);
    $customer->delete();

    $this->get(route('vehicle.public', $vehicle->public_token))
        ->assertOk()
        ->assertSee($vehicle->license_plate);
});

it('la ficha QR imprimible abre aunque el cliente esté borrado', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $vehicle  = Vehicle::factory()->create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $customer->id,
    ]);
    $customer->delete();

    $this->get(route('vehicles.qr-card', $vehicle))->assertOk();
});
