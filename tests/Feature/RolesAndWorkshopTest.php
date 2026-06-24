<?php
use App\Filament\Pages\WorkshopSettings;
use App\Models\{Customer,Tenant,User,Vehicle,WorkOrder};
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function mkUser(string $role, Tenant $t): User {
    Role::findOrCreate($role);
    $u = User::factory()->create(['tenant_id'=>$t->id,'is_super_admin'=>false]);
    $u->assignRole($role);
    return $u;
}

beforeEach(function(){
    $this->tenant = Tenant::factory()->create([
        'primary_color' => '#0f766e',
        'secondary_color' => '#f5f5f5',
    ]);
    app()->instance('current.tenant', $this->tenant);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

it('el rol manager ya no existe en el modelo de roles', function(){
    expect(\App\Support\Roles::WORKSHOP)->not->toContain('manager')
        ->and(\App\Support\Roles::WORKSHOP)->toEqual(['admin','receptionist','mechanic']);
});

it('recepcionista puede eliminar clientes y órdenes', function(){
    $u = mkUser('receptionist', $this->tenant);
    $c = Customer::factory()->create(['tenant_id'=>$this->tenant->id]);
    $v = Vehicle::factory()->create(['tenant_id'=>$this->tenant->id,'customer_id'=>$c->id]);
    $wo = WorkOrder::factory()->create(['tenant_id'=>$this->tenant->id,'customer_id'=>$c->id,'vehicle_id'=>$v->id]);
    expect($u->can('delete', $c))->toBeTrue()
        ->and($u->can('delete', $wo))->toBeTrue();
});

it('mecánico no puede eliminar órdenes pero sí editarlas', function(){
    $u = mkUser('mechanic', $this->tenant);
    $c = Customer::factory()->create(['tenant_id'=>$this->tenant->id]);
    $v = Vehicle::factory()->create(['tenant_id'=>$this->tenant->id,'customer_id'=>$c->id]);
    $wo = WorkOrder::factory()->create(['tenant_id'=>$this->tenant->id,'customer_id'=>$c->id,'vehicle_id'=>$v->id]);
    expect($u->can('delete', $wo))->toBeFalse()
        ->and($u->can('update', $wo))->toBeTrue()
        ->and($u->can('viewAny', Customer::class))->toBeFalse();
});

it('solo el admin accede a Mi Taller', function(){
    $admin = mkUser('admin', $this->tenant);
    $recep = mkUser('receptionist', $this->tenant);
    $this->actingAs($admin);
    expect(WorkshopSettings::canAccess())->toBeTrue();
    $this->actingAs($recep);
    expect(WorkshopSettings::canAccess())->toBeFalse();
});

it('el admin guarda preferencias del taller', function(){
    $admin = mkUser('admin', $this->tenant);
    $this->actingAs($admin);
    Livewire::test(WorkshopSettings::class)
        ->set('data.name', 'Taller Nuevo Nombre')
        ->set('data.phone', '011-5555-0000')
        ->call('save')
        ->assertHasNoFormErrors();
    expect($this->tenant->fresh()->name)->toBe('Taller Nuevo Nombre')
        ->and($this->tenant->fresh()->phone)->toBe('011-5555-0000');
});
