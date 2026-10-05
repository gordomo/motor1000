<?php

/**
 * Pedido del cliente: en los montos y en el horario de los turnos, sin flechitas
 * y sin que la ruedita del mouse cambie el número (generaba errores sin querer).
 * El comportamiento se verificó en Chrome y Firefox; acá se asegura que el panel
 * siga cargando el CSS y el script.
 */

use App\Filament\Resources\AppointmentResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('el panel oculta las flechitas y bloquea la ruedita en campos numéricos y de fecha', function () {
    \Spatie\Permission\Models\Role::findOrCreate('receptionist');
    $t = Tenant::factory()->create();
    app()->instance('current.tenant', $t);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $u = User::factory()->create(['tenant_id' => $t->id]);
    $u->assignRole('receptionist');

    $this->actingAs($u)
        ->get(AppointmentResource::getUrl('create'))
        ->assertOk()
        ->assertSee('input[type="number"]::-webkit-inner-spin-button', false)
        ->assertSee("['number', 'time', 'date', 'datetime-local'].includes(campo.type)", false)
        ->assertSee('campo.blur()', false);
});
