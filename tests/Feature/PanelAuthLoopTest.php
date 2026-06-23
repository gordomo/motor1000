<?php

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Regresión: CurrentTenant::get() -> auth()->user() dispara una consulta sobre
 * el modelo User (con TenantScope) que vuelve a llamar a get(), recursando hasta
 * agotar la memoria (500 en /admin). Solo se reproduce con resolución real del
 * usuario desde la sesión (NO con actingAs, que cachea el usuario). Por eso estos
 * tests entran por sesión y se corren bien con poca memoria.
 */
function sessionLoginKey(): string
{
    return 'login_web_' . sha1(\Illuminate\Auth\SessionGuard::class);
}

it('el super-admin entra a /admin sin recursión', function () {
    Role::findOrCreate('admin');
    $super = User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);

    $res = $this->withSession([sessionLoginKey() => $super->id])->get('/admin');

    expect($res->getStatusCode())->toBeLessThan(500);
});

it('el usuario de taller entra a /painel sin recursión', function () {
    Role::findOrCreate('admin');
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_super_admin' => false]);
    $user->assignRole('admin');

    $res = $this->withSession([sessionLoginKey() => $user->id])->get('/painel');

    expect($res->getStatusCode())->toBeLessThan(500);
});
