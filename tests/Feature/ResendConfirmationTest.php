<?php

use App\Filament\Resources\AppointmentResource\Pages\ListAppointments;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('reenvía la confirmación por mail desde el CRM', function () {
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $t = Tenant::factory()->create();
    $u = User::factory()->create(['tenant_id' => $t->id]);
    $u->assignRole('admin');
    $c = Customer::factory()->create(['tenant_id' => $t->id, 'email' => 'cliente@example.com']);
    $v = Vehicle::factory()->create(['tenant_id' => $t->id, 'customer_id' => $c->id]);
    $a = Appointment::create([
        'tenant_id' => $t->id, 'customer_id' => $c->id, 'vehicle_id' => $v->id,
        'title' => 'Frenos', 'status' => 'scheduled',
        'scheduled_at' => now()->addDay(), 'duration_minutes' => 30,
    ]);

    app()->instance('current.tenant', $t);
    $this->actingAs($u);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Mail::fake();
    Livewire::test(ListAppointments::class)->callTableAction('reenviar_confirmacion', $a);
    Mail::assertQueued(AppointmentConfirmationMail::class);
});
