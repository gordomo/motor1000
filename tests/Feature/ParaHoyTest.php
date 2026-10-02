<?php

/**
 * "Para hoy": recordatorios para el usuario del sistema (cumpleaños,
 * recordatorios vencidos, turnos de mañana). El sistema no le escribe al
 * cliente mientras WhatsApp no esté conectado, y nada figura como enviado
 * si no salió.
 */

use App\Filament\Pages\ParaHoyPage;
use App\Filament\Widgets\ParaHoy\CumpleanosHoy;
use App\Filament\Widgets\ParaHoy\RecordatoriosHoy;
use App\Filament\Widgets\ParaHoy\TurnosManana;
use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use App\Models\Communication;
use App\Models\Customer;
use App\Models\Reminder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\ParaHoyNotification;
use App\Support\ParaHoy;
use App\Support\WhatsAppLink;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 2)->setTime(10, 0));

    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create(['name' => '341 Boxes']);
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->admin = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->cliente = fn (array $datos = []) => Customer::factory()->create(array_merge([
        'tenant_id' => $this->t->id, 'name' => 'Juan Pérez', 'whatsapp' => '223 555-1234',
        'whatsapp_opted_in' => true, 'birthday' => null,
    ], $datos));
});

// ---------------------------------------------------------------- links

it('arma el link de WhatsApp con el número argentino completo', function (?string $cargado, ?string $esperado) {
    expect(WhatsAppLink::normalizar($cargado))->toBe($esperado);
})->with([
    'local con guión'        => ['223 555-1234', '5492235551234'],
    'con 0 adelante'         => ['0223 5551234', '5492235551234'],
    'internacional con 9'    => ['+54 9 223 555 1234', '5492235551234'],
    'internacional sin 9'    => ['+54 223 555 1234', '5492235551234'],
    'con 00'                 => ['0054 9 11 1234 5678', '5491112345678'],
    'sin código de área'     => ['555-1234', null],
    'con el 15 (ambiguo)'    => ['0223 15 555 1234', null],
    'vacío'                  => [null, null],
]);

// ---------------------------------------------------------------- qué entra

it('cumpleaños: los de hoy, y deja de contarlo cuando ya se atendió este año', function () {
    $hoy      = ($this->cliente)(['birthday' => '1990-10-02']);
    $atendido = ($this->cliente)(['birthday' => '1985-10-02', 'birthday_contacted_at' => now()->subHour()]);
    $pasado   = ($this->cliente)(['birthday' => '1980-10-02', 'birthday_contacted_at' => now()->subYear()]);
    ($this->cliente)(['birthday' => '1990-10-03']);

    expect(ParaHoy::cumpleanos()->pluck('id')->sort()->values()->all())->toBe(collect([$hoy->id, $atendido->id, $pasado->id])->sort()->values()->all())
        ->and(ParaHoy::cumpleanosPendientes()->pluck('id')->sort()->values()->all())->toBe(collect([$hoy->id, $pasado->id])->sort()->values()->all());
});

it('recordatorios: los pendientes que vencen hoy o ya vencieron', function () {
    $c = ($this->cliente)();
    $r = fn (string $due, string $status = 'pending') => Reminder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $c->id, 'type' => 'oil_change',
        'title' => 'Cambio de aceite', 'trigger_type' => 'date', 'due_at' => $due, 'status' => $status,
    ]);
    $hoy = $r('2026-10-02 18:00');
    $vencido = $r('2026-09-20 09:00');
    $r('2026-10-03 09:00');
    $r('2026-09-20 09:00', 'completed');

    expect(ParaHoy::recordatorios()->pluck('id')->sort()->values()->all())->toBe([$hoy->id, $vencido->id]);
});

it('turnos: los de mañana sin cancelados, y cuenta los que falta confirmar', function () {
    $c = ($this->cliente)();
    $v = Vehicle::factory()->create(['tenant_id' => $this->t->id, 'customer_id' => $c->id]);
    $t = fn (string $cuando, string $status, $confirmo = null) => Appointment::create([
        'tenant_id' => $this->t->id, 'customer_id' => $c->id, 'vehicle_id' => $v->id, 'title' => 'Service',
        'scheduled_at' => $cuando, 'duration_minutes' => 60, 'status' => $status, 'client_confirmed_at' => $confirmo,
    ]);
    $sinConfirmar = $t('2026-10-03 09:00', 'scheduled');
    $t('2026-10-03 11:00', 'confirmed', now());
    $t('2026-10-03 12:00', 'cancelled');
    $t('2026-10-02 15:00', 'scheduled');

    expect(ParaHoy::turnosManana()->count())->toBe(2)
        ->and(ParaHoy::turnosSinConfirmar()->pluck('id')->all())->toBe([$sinConfirmar->id]);
});

it('los mensajes están en español y con el nombre del taller', function () {
    $c = ($this->cliente)(['birthday' => '1990-10-02']);

    expect(ParaHoy::mensajeCumpleanos($c))->toBe('¡Hola Juan! Desde 341 Boxes te deseamos un muy feliz cumpleaños. ¡Que lo pases genial!');
});

// ---------------------------------------------------------------- pantalla

it('admin y recepción ven Para hoy; el mecánico no', function () {
    $this->get(ParaHoyPage::getUrl())->assertOk()->assertSee('Cumpleaños')->assertSee('Turnos de mañana');

    $mecanico = User::factory()->create(['tenant_id' => $this->t->id]);
    $mecanico->assignRole('mechanic');
    $this->actingAs($mecanico);

    expect(ParaHoyPage::canAccess())->toBeFalse()
        ->and(ParaHoyPage::shouldRegisterNavigation())->toBeFalse();
});

it('saludar por WhatsApp y marcar Listo saca el cumpleaños de pendientes', function () {
    $c = ($this->cliente)(['birthday' => '1990-10-02']);

    Livewire::test(CumpleanosHoy::class)
        ->assertCanSeeTableRecords([$c])
        ->assertTableActionVisible('whatsapp', $c)
        ->callTableAction('listo', $c)
        ->assertDispatched('para-hoy-actualizado');

    expect($c->fresh()->birthday_contacted_at)->not->toBeNull()
        ->and(ParaHoy::cumpleanosPendientes()->count())->toBe(0);
});

it('sin número completo no ofrece WhatsApp (no abre un chat equivocado)', function () {
    $c = ($this->cliente)(['birthday' => '1990-10-02', 'whatsapp' => '555-1234', 'phone' => null]);

    Livewire::test(CumpleanosHoy::class)->assertTableActionHidden('whatsapp', $c);
});

it('recordatorio Hecho y turno Confirmó actualizan el registro', function () {
    $c = ($this->cliente)();
    $rec = Reminder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $c->id, 'type' => 'oil_change',
        'title' => 'Cambio de aceite', 'trigger_type' => 'date', 'due_at' => now(), 'status' => 'pending',
    ]);
    $turno = Appointment::create([
        'tenant_id' => $this->t->id, 'customer_id' => $c->id, 'title' => 'Service',
        'scheduled_at' => now()->addDay(), 'duration_minutes' => 60, 'status' => 'scheduled',
    ]);

    Livewire::test(RecordatoriosHoy::class)->callTableAction('hecho', $rec);
    Livewire::test(TurnosManana::class)->callTableAction('confirmado', $turno);

    expect($rec->fresh()->status)->toBe('completed')
        ->and($turno->fresh()->status)->toBe('confirmed')
        ->and($turno->fresh()->client_confirmed_at)->not->toBeNull();
});

// ---------------------------------------------------------------- campanita

it('a las 8 avisa a admin y recepción, no al mecánico', function () {
    Notification::fake();
    ($this->cliente)(['birthday' => '1990-10-02']);

    $recepcion = User::factory()->create(['tenant_id' => $this->t->id]);
    $recepcion->assignRole('receptionist');
    $mecanico = User::factory()->create(['tenant_id' => $this->t->id]);
    $mecanico->assignRole('mechanic');

    $this->artisan('para-hoy:avisar')->assertSuccessful();

    Notification::assertSentTo([$this->admin, $recepcion], ParaHoyNotification::class);
    Notification::assertNotSentTo($mecanico, ParaHoyNotification::class);
});

it('si no hay nada para hoy, no molesta', function () {
    Notification::fake();

    $this->artisan('para-hoy:avisar')->assertSuccessful();

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------- nada "enviado" si no salió

it('sin WhatsApp conectado, el aviso de turno no se registra ni marca el turno como avisado', function () {
    config(['services.whatsapp.provider' => 'log']);
    $c = ($this->cliente)();
    $turno = Appointment::create([
        'tenant_id' => $this->t->id, 'customer_id' => $c->id, 'title' => 'Service',
        'scheduled_at' => now()->addHours(24), 'duration_minutes' => 60, 'status' => 'scheduled',
    ]);

    app()->call([new SendAppointmentReminderJob, 'handle']);

    expect($turno->fresh()->reminder_sent)->toBeFalsy()
        ->and(Communication::count())->toBe(0);
});

it('con WhatsApp conectado, sí lo manda (en español) y marca el turno', function () {
    config(['services.whatsapp.provider' => 'twilio']);
    Queue::fake();
    $c = ($this->cliente)();
    $turno = Appointment::create([
        'tenant_id' => $this->t->id, 'customer_id' => $c->id, 'title' => 'Service',
        'scheduled_at' => now()->addHours(24), 'duration_minutes' => 60, 'status' => 'scheduled',
    ]);

    app()->call([new SendAppointmentReminderJob, 'handle']);

    expect($turno->fresh()->reminder_sent)->toBeTruthy()
        ->and(Communication::sole()->body)->toStartWith('Hola Juan Pérez, te recordamos tu turno de mañana a las 10:00');
});
