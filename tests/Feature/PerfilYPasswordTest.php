<?php

/**
 * Perfil y recuperación de contraseña.
 *
 * Antes los paneles solo tenían ->login(): nadie podía cambiar su propia
 * contraseña y recuperar el acceso dependía de correr un comando en el servidor.
 */

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create();
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->user = User::factory()->create([
        'tenant_id' => $this->t->id,
        'email'     => 'comercial@ejemplo.test',
        'password'  => Hash::make('vieja-password'),
    ]);
    $this->user->assignRole('receptionist');
});

it('la pantalla de login ofrece recuperar la contraseña', function () {
    $this->get('/panel/login')
        ->assertOk()
        ->assertSee(route('filament.app.auth.password-reset.request'), escape: false);
});

it('pedir el reset le manda el mail al usuario', function () {
    Notification::fake();

    $status = Password::broker()->sendResetLink(['email' => $this->user->email]);

    expect($status)->toBe(Password::RESET_LINK_SENT);

    Notification::assertSentTo($this->user, ResetPassword::class);
});

it('el scope de taller no impide encontrar al usuario que pide el reset', function () {
    // El modelo User tiene scope de taller. Como quien pide el reset no está
    // logueado, no hay taller resuelto y la búsqueda tiene que funcionar igual.
    app()->forgetInstance('current.tenant');
    Notification::fake();

    expect(Password::broker()->sendResetLink(['email' => $this->user->email]))
        ->toBe(Password::RESET_LINK_SENT);
});

it('el reset cambia la contraseña de verdad', function () {
    $token = Password::broker()->createToken($this->user);

    $status = Password::broker()->reset(
        [
            'email'                 => $this->user->email,
            'password'              => 'nueva-password-123',
            'password_confirmation' => 'nueva-password-123',
            'token'                 => $token,
        ],
        function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        },
    );

    expect($status)->toBe(Password::PASSWORD_RESET)
        ->and(Hash::check('nueva-password-123', $this->user->refresh()->password))->toBeTrue();
});

it('cualquier usuario del taller entra a su perfil', function () {
    $this->actingAs($this->user);

    $this->get(route('filament.app.auth.profile'))->assertOk();
});

it('el mecánico también puede cambiar su contraseña', function () {
    $mecanico = User::factory()->create(['tenant_id' => $this->t->id]);
    $mecanico->assignRole('mechanic');
    $this->actingAs($mecanico);

    $this->get(route('filament.app.auth.profile'))->assertOk();
});

it('el super admin tiene perfil y recuperación en su propio panel', function () {
    $super = User::factory()->create(['tenant_id' => null, 'is_super_admin' => true]);
    $this->actingAs($super);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get(route('filament.admin.auth.profile'))->assertOk();

    expect(route('filament.admin.auth.password-reset.request'))->toBeString();
});
