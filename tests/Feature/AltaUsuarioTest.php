<?php

/**
 * Alta de usuarios desde el panel del taller.
 *
 * Incluye la regresión del autocompletado de Chrome: llenaba nombre y correo sin
 * disparar el evento de Livewire, así que el formulario mostraba los datos pero
 * el servidor los recibía vacíos y pedía los campos igual.
 */

use App\Filament\Resources\UserResource\Pages\CreateUser;
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

    $this->admin = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

it('el administrador crea un usuario mecánico', function () {
    $rolMecanico = \Spatie\Permission\Models\Role::where('name', 'mechanic')->first();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'      => 'Mecanico',
            'email'     => 'mecanico@341boxes.ar',
            'roles'     => [$rolMecanico->id],
            'password'  => '341Mec5555',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $creado = User::withoutGlobalScopes()->where('email', 'mecanico@341boxes.ar')->first();

    expect($creado)->not->toBeNull()
        ->and($creado->tenant_id)->toBe($this->t->id)
        ->and($creado->is_super_admin)->toBeFalse()
        ->and($creado->hasRole('mechanic'))->toBeTrue()
        ->and(Hash::check('341Mec5555', $creado->password))->toBeTrue();
});

it('el administrador puede crear los tres roles del taller', function () {
    foreach (['admin' => 'a@t.test', 'receptionist' => 'r@t.test', 'mechanic' => 'm@t.test'] as $rol => $email) {
        $rolId = \Spatie\Permission\Models\Role::where('name', $rol)->first()->id;

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => ucfirst($rol), 'email' => $email, 'roles' => [$rolId],
                'password' => 'password123', 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(User::withoutGlobalScopes()->where('email', $email)->first()->hasRole($rol))->toBeTrue();
    }
});

it('no deja crear dos usuarios con el mismo correo', function () {
    $rolId = \Spatie\Permission\Models\Role::where('name', 'mechanic')->first()->id;

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Repetido', 'email' => $this->admin->email, 'roles' => [$rolId],
            'password' => 'password123', 'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);
});

it('los mensajes de validación salen en castellano', function () {
    // Faltaba lang/es/ y Laravel caía al inglés: "The nombre field is required."
    app()->setLocale('es');

    expect(trans('validation.required', ['attribute' => 'Nombre']))
        ->toBe('El campo Nombre es obligatorio.')
        ->and(trans('validation.unique', ['attribute' => 'Correo electrónico']))
        ->toBe('El campo Correo electrónico ya está en uso.');
});
