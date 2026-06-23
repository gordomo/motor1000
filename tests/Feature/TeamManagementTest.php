<?php

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (\App\Support\Roles::WORKSHOP as $r) {
        Role::findOrCreate($r);
    }
    $this->tenant = Tenant::factory()->create();
    app()->instance('current.tenant', $this->tenant);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

function actingAsRole(string $role, Tenant $tenant): User
{
    $u = User::factory()->create(['tenant_id' => $tenant->id]);
    $u->assignRole($role);
    test()->actingAs($u);
    return $u;
}

it('la página de alta de equipo monta sin error (incluido el select de roles)', function () {
    actingAsRole('admin', $this->tenant);

    Livewire::test(CreateUser::class)
        ->assertOk()
        ->assertFormExists()
        ->assertFormFieldExists('roles');
});

it('al asignar un rol el usuario queda con permisos del taller', function () {
    actingAsRole('admin', $this->tenant);

    // Simula el alta que hace el resource: usuario del taller + rol vía relación.
    $nuevo = User::create([
        'tenant_id' => $this->tenant->id,
        'name'      => 'Recepción Uno',
        'email'     => 'recepcion@taller.test',
        'password'  => 'secret123',
        'is_active' => true,
        'is_super_admin' => false,
    ]);
    $nuevo->syncRoles(['receptionist']);

    expect($nuevo->fresh()->tenant_id)->toBe($this->tenant->id)
        ->and($nuevo->is_super_admin)->toBeFalse()
        ->and($nuevo->hasRole('receptionist'))->toBeTrue();
});

it('un mecánico NO puede acceder a la gestión de equipo', function () {
    actingAsRole('mechanic', $this->tenant);
    expect(\App\Filament\Resources\UserResource::canViewAny())->toBeFalse();
});

it('un admin SÍ puede acceder a la gestión de equipo', function () {
    actingAsRole('admin', $this->tenant);
    expect(\App\Filament\Resources\UserResource::canViewAny())->toBeTrue();
});

it('la lista de equipo solo muestra usuarios del propio taller', function () {
    actingAsRole('admin', $this->tenant);
    $otroTaller = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $otroTaller->id, 'email' => 'ajeno@otro.test']);

    Livewire::test(ListUsers::class)
        ->assertCanNotSeeTableRecords(User::withoutGlobalScopes()->where('email', 'ajeno@otro.test')->get());
});
