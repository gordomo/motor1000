<?php
use App\Filament\Resources\AppointmentResource\Pages\CreateAppointment;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Models\{Customer,Tenant,User};
use Filament\Facades\Filament;
use Livewire\Livewire;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);
beforeEach(function(){
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $this->t=Tenant::factory()->create();
    $this->u=User::factory()->create(['tenant_id'=>$this->t->id]); $this->u->assignRole('admin');
    app()->instance('current.tenant',$this->t); $this->actingAs($this->u);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});
it('las páginas de alta rápida (cliente y cita) renderizan sin error', function(){
    Livewire::test(CreateCustomer::class)->assertOk();
    Livewire::test(CreateAppointment::class)->assertOk()
        ->assertFormSet(fn (array $s): bool => !empty($s['scheduled_at'])); // fecha precargada
});
it('5: el form de cita precarga el cliente del query', function(){
    $c=Customer::factory()->create(['tenant_id'=>$this->t->id]);
    Livewire::withQueryParams(['customer_id'=>$c->id])->test(CreateAppointment::class)
        ->assertFormSet(['customer_id'=>$c->id]);
});
