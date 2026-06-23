<?php

use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (\App\Support\Roles::WORKSHOP as $r) {
        Role::findOrCreate($r);
    }
    $this->super = User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    $this->actingAs($this->super);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// El alta de usuarios del panel /admin debe incluir el selector de roles y montar bien.
it('renders the admin create-user form with the roles field', function () {
    Livewire::test(CreateUser::class)
        ->assertOk()
        ->assertFormFieldExists('roles');
});
