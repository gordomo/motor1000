<?php

use App\Actions\WorkOrder\CancelWorkOrderAction;
use App\Actions\WorkOrder\UpdateWorkOrderStatusAction;
use App\Enums\WorkOrderStatus;
use App\Filament\Pages\AppointmentsCalendar;
use App\Filament\Pages\WorkshopSettings;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Filament\Resources\WorkOrderResource\Pages\ViewWorkOrder;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\DashboardService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
    $this->t = Tenant::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires', 'primary_color' => '#1e5aa8', 'secondary_color' => '#f5f5f5']);
    app()->instance('current.tenant', $this->t);
    config(['app.timezone' => $this->t->timezone]);
    foreach (['admin', 'receptionist', 'mechanic'] as $role) {
        \Spatie\Permission\Models\Role::findOrCreate($role);
    }
    $this->admin = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id, 'last_visit_at' => now()]);
    $this->vehicle = Vehicle::factory()->create(['tenant_id' => $this->t->id, 'customer_id' => $this->customer->id]);
    $this->order = WorkOrder::factory()->create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'received', 'completed_at' => null,
    ]);
});

it('el calendario publica la hora del taller y el arrastre conserva esa hora', function () {
    $appointment = Appointment::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'title' => 'Service', 'scheduled_at' => '2026-11-16 09:00:00',
        'ends_at' => '2026-11-16 10:00:00', 'duration_minutes' => 60, 'status' => 'scheduled',
    ]);
    Livewire::test(AppointmentsCalendar::class)
        ->assertSet('events.0.start', '2026-11-16T09:00:00')
        ->call('updateAppointmentSchedule', $appointment->id, '2026-11-16 11:30:00', '2026-11-16 12:30:00');
    expect($appointment->fresh()->scheduled_at->format('H:i'))->toBe('11:30')
        ->and($appointment->fresh()->ends_at->format('H:i'))->toBe('12:30');

    // También normaliza clientes anteriores que mandan ISO con offset.
    Livewire::test(AppointmentsCalendar::class)
        ->call('updateAppointmentSchedule', $appointment->id, '2026-11-16T15:00:00+01:00', '2026-11-16T16:00:00+01:00');
    expect($appointment->fresh()->scheduled_at->format('H:i'))->toBe('11:00');
});

it('la anulación conserva número autor fecha motivo e historial y sale de los indicadores', function () {
    $number = $this->order->number;
    $cancelled = app(CancelWorkOrderAction::class)->execute($this->order, '  Carga duplicada  ');
    expect($cancelled->status)->toBe(WorkOrderStatus::Cancelled)
        ->and($cancelled->number)->toBe($number)
        ->and($cancelled->cancelled_reason)->toBe('Carga duplicada')
        ->and($cancelled->cancelled_by)->toBe($this->admin->id)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->deleted_at)->toBeNull()
        ->and($cancelled->statusHistory()->sole()->comment)->toBe('Carga duplicada')
        ->and($cancelled->statusHistory()->sole()->user_id)->toBe($this->admin->id)
        ->and(app(DashboardService::class)->getKpis($this->t->id)['open_work_orders'])->toBe(0);

    $this->get(ViewWorkOrder::getUrl(['record' => $cancelled]))
        ->assertOk()->assertSee('Carga duplicada')->assertSee($this->admin->name);
    Livewire::test(ListWorkOrders::class, ['activeTab' => 'cancelled'])
        ->assertCanSeeTableRecords([$cancelled]);
    expect(WorkOrder::generateNumber($this->t->id))->not->toBe($number)
        ->and($this->admin->can('delete', $cancelled))->toBeFalse()
        ->and($this->admin->can('update', $cancelled))->toBeFalse();
});

it('anular exige motivo y permisos también desde la acción del servidor', function () {
    expect(fn () => app(CancelWorkOrderAction::class)->execute($this->order, '  '))
        ->toThrow(ValidationException::class);
    foreach (['receptionist', 'mechanic'] as $role) {
        $user = User::factory()->create(['tenant_id' => $this->t->id]);
        $user->assignRole($role);
        $this->actingAs($user);
        expect(fn () => app(CancelWorkOrderAction::class)->execute($this->order, 'Duplicada'))
            ->toThrow(AuthorizationException::class);
        expect($user->can('delete', $this->order))->toBeFalse();
    }
    expect($this->order->fresh()->status)->toBe(WorkOrderStatus::Received);
});

it('el administrador de otro taller no puede anular', function () {
    $other = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $other->assignRole('admin');
    $this->actingAs($other);
    expect(fn () => app(CancelWorkOrderAction::class)->execute($this->order, 'Ajena'))
        ->toThrow(AuthorizationException::class);
});

it('se bloquea la anulación con cobros sin borrar ni alterar el dinero', function () {
    Payment::create(['tenant_id' => $this->t->id, 'work_order_id' => $this->order->id,
        'amount' => 5000, 'method' => 'efectivo']);
    expect(fn () => app(CancelWorkOrderAction::class)->execute($this->order, 'Duplicada'))
        ->toThrow(ValidationException::class);
    expect($this->order->fresh()->status)->toBe(WorkOrderStatus::Received)
        ->and($this->order->payments()->sum('amount'))->toEqual(5000);
});

it('se bloquea la anulación con insumos consumidos sin devolver stock ficticio', function () {
    $this->order->items()->create(['type' => 'part', 'description' => 'Filtro',
        'quantity' => 1, 'unit_price' => 1000, 'total' => 1000, 'stock_applied_at' => now()]);
    expect(fn () => app(CancelWorkOrderAction::class)->execute($this->order, 'Duplicada'))
        ->toThrow(ValidationException::class);
    expect($this->order->fresh()->status)->toBe(WorkOrderStatus::Received)
        ->and($this->order->items()->sole()->stock_applied_at)->not->toBeNull();
});

it('una orden anulada no se reactiva desde el kanban ni acepta cobros', function () {
    $stale = $this->order->fresh();
    app(CancelWorkOrderAction::class)->execute($this->order, 'Duplicada');
    expect(fn () => app(UpdateWorkOrderStatusAction::class)->execute($stale, WorkOrderStatus::Repairing))
        ->toThrow(DomainException::class);
    expect(fn () => Payment::create(['tenant_id' => $this->t->id, 'work_order_id' => $this->order->id,
        'amount' => 1000, 'method' => 'efectivo']))->toThrow(ValidationException::class);
});

it('el botón Anular exige motivo y no existen acciones de borrado', function () {
    Livewire::test(ViewWorkOrder::class, ['record' => $this->order->id])
        ->assertActionDoesNotExist('delete')
        ->callAction('cancel', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);
    Livewire::test(ListWorkOrders::class)
        ->assertTableBulkActionDoesNotExist('delete')
        ->callTableAction('cancel', $this->order, data: ['reason' => 'Error de carga'])
        ->assertHasNoTableActionErrors();
    expect($this->order->fresh()->isCancelled())->toBeTrue();
});

it('inactivos filtros y ficha comparten 12 meses y nunca vinieron queda como prospecto', function () {
    $old = Customer::factory()->create(['tenant_id' => $this->t->id, 'status' => 'active', 'last_visit_at' => now()->subMonths(13)]);
    $recent = Customer::factory()->create(['tenant_id' => $this->t->id, 'status' => 'inactive', 'last_visit_at' => now()->subMonths(7)]);
    $never = Customer::factory()->create(['tenant_id' => $this->t->id, 'status' => 'active', 'last_visit_at' => null]);
    expect($old->relationshipStatus())->toBe('inactive')
        ->and($recent->relationshipStatus())->toBe('active')
        ->and($never->relationshipStatus())->toBe('prospect')
        ->and(Customer::inactive()->pluck('id')->all())->toBe([$old->id]);
    Livewire::test(ListCustomers::class, ['activeTab' => 'inactive'])
        ->assertCanSeeTableRecords([$old])->assertCanNotSeeTableRecords([$recent, $never]);
    Livewire::test(ListCustomers::class)
        ->filterTable('status', 'inactive')
        ->assertCanSeeTableRecords([$old])->assertCanNotSeeTableRecords([$recent, $never]);
    Livewire::test(EditCustomer::class, ['record' => $old->id])
        ->assertFormFieldDoesNotExist('status');
    expect(app(DashboardService::class)->getKpis($this->t->id)['inactive_customers'])->toBe(1);
});

it('cambiar el plazo recalcula los clientes y conserva el resto de preferencias', function () {
    $this->t->update(['settings' => ['other_setting' => 'conservar']]);
    $customer = Customer::factory()->create(['tenant_id' => $this->t->id, 'last_visit_at' => now()->subMonths(8)]);
    expect($customer->isInactive())->toBeFalse();
    Livewire::test(WorkshopSettings::class)->set('data.inactive_customer_months', 6)
        ->call('save')->assertHasNoFormErrors();
    expect($this->t->fresh()->getSetting('other_setting'))->toBe('conservar')
        ->and($this->t->fresh()->getSetting('inactive_customer_months'))->toBe(6)
        ->and($customer->isInactive())->toBeTrue()
        ->and(Customer::inactive()->whereKey($customer)->exists())->toBeTrue();
});
