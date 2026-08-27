<?php

/**
 * Flujo de órdenes de trabajo definido con el cliente:
 *  - estados con dueño: el mecánico trabaja, el comercial entrega
 *  - datos obligatorios al avanzar: trabajo realizado, checklist, forma de pago
 *  - la orden trabada queda marcada sin cambiar de columna
 *  - entregada = cobro registrado (salvo que sea gratis)
 *  - presupuestos: borrador y enviado unificados en Pendiente de aprobación
 */

use App\Actions\WorkOrder\UpdateWorkOrderStatusAction;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Support\WorkOrderTransitions;
use Filament\Facades\Filament;

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
    $this->comercial = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->comercial->assignRole('receptionist');
    $this->mecanicoUser = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->mecanicoUser->assignRole('mechanic');

    $this->mecanico = Mechanic::create([
        'tenant_id' => $this->t->id, 'name' => 'Juan Mecánico', 'is_active' => true,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
    ]);

    $this->actingAs($this->admin);
});

/** Orden lista para el paso que se quiera probar. */
function orden(array $attrs = []): WorkOrder
{
    return WorkOrder::create(array_merge([
        'tenant_id'   => test()->t->id,
        'customer_id' => test()->customer->id,
        'vehicle_id'  => test()->vehicle->id,
        'status'      => 'received',
        'complaint'   => 'Ruido raro',
        'mileage_in'  => 50000,
    ], $attrs));
}

// ─── Quién puede mover qué ──────────────────────────────────────────────────

it('el mecánico pone la orden en reparación y queda asignada a él', function () {
    $o = orden();
    $this->actingAs($this->mecanicoUser);

    app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Repairing, options: [
        'mechanic_id' => $this->mecanico->id,
    ]);

    $o->refresh();

    expect($o->status)->toBe(WorkOrderStatus::Repairing)
        ->and($o->mechanic_id)->toBe($this->mecanico->id)
        ->and($o->started_at)->not->toBeNull()
        // El historial guarda qué mecánico lo hizo, no solo el usuario del totem.
        ->and($o->statusHistory()->latest('id')->first()->mechanic_id)->toBe($this->mecanico->id);
});

it('el mecánico NO puede entregar la orden', function () {
    $o = orden(['status' => 'completed', 'total' => 50000, 'work_performed' => 'Listo']);
    $this->actingAs($this->mecanicoUser);

    expect(fn () => app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Delivered))
        ->toThrow(DomainException::class);

    expect($o->refresh()->status)->toBe(WorkOrderStatus::Completed);
});

it('el comercial entrega la orden y eso registra el cobro', function () {
    $o = orden(['status' => 'completed', 'total' => 50000, 'work_performed' => 'Cambio de pastillas']);
    $this->actingAs($this->comercial);

    app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Delivered, options: [
        'payment' => ['amount' => 50000, 'method' => 'efectivo'],
    ]);

    $o->refresh();

    expect($o->status)->toBe(WorkOrderStatus::Delivered)
        ->and($o->totalPaid())->toEqual(50000.0)
        ->and($o->balance())->toEqual(0.0)
        ->and($o->payment_status)->toBe('paid');
});

it('el comercial NO puede poner la orden en reparación', function () {
    $o = orden();
    $this->actingAs($this->comercial);

    expect(fn () => app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Repairing))
        ->toThrow(DomainException::class);
});

it('el administrador puede hacer cualquier paso', function () {
    expect(WorkOrderTransitions::userCanMove($this->admin, WorkOrderStatus::Received, WorkOrderStatus::Repairing))->toBeTrue()
        ->and(WorkOrderTransitions::userCanMove($this->admin, WorkOrderStatus::Completed, WorkOrderStatus::Delivered))->toBeTrue();
});

// ─── Datos obligatorios al avanzar ──────────────────────────────────────────

it('no se completa la orden sin escribir el trabajo realizado', function () {
    $o = orden(['status' => 'repairing']);

    expect(fn () => app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Completed))
        ->toThrow(DomainException::class, 'Falta escribir el trabajo realizado.');
});

it('no se completa la orden con puntos del checklist sin resolver', function () {
    $o = orden([
        'status'         => 'repairing',
        'work_performed' => 'Se cambiaron las pastillas',
        'checklist'      => [
            ['id_punto' => 1, 'categoria' => 'Frenos', 'nombre_item' => 'Pastillas', 'estado' => 'BIEN', 'aclaracion' => ''],
            ['id_punto' => 2, 'categoria' => 'Luces',  'nombre_item' => 'Bocina',    'estado' => null,   'aclaracion' => ''],
        ],
    ]);

    expect(fn () => app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Completed))
        ->toThrow(DomainException::class);

    expect(WorkOrderTransitions::pendingChecklistPoints($o))->toBe(1);
});

it('se completa la orden si el punto que no se pudo hacer está marcado', function () {
    $o = orden([
        'status'         => 'repairing',
        'work_performed' => 'Se cambiaron las pastillas',
        'checklist'      => [
            ['id_punto' => 1, 'categoria' => 'Frenos', 'nombre_item' => 'Pastillas', 'estado' => 'BIEN', 'aclaracion' => ''],
            ['id_punto' => 2, 'categoria' => 'Luces',  'nombre_item' => 'Bocina',    'estado' => 'NO_SE_PUDO', 'aclaracion' => 'Falta el repuesto'],
        ],
    ]);

    app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Completed);

    expect($o->refresh()->status)->toBe(WorkOrderStatus::Completed);
});

// ─── Orden trabada ──────────────────────────────────────────────────────────

it('la orden trabada no arranca y queda marcada sin cambiar de columna', function () {
    $o = orden();
    $o->block('Falta el filtro de aceite');

    expect($o->isBlocked())->toBeTrue()
        ->and($o->blocked_at)->not->toBeNull();

    expect(fn () => app(UpdateWorkOrderStatusAction::class)->execute($o->fresh(), WorkOrderStatus::Repairing))
        ->toThrow(DomainException::class);

    // Sigue en Recibido: no se inventó una columna nueva para esto.
    expect($o->refresh()->status)->toBe(WorkOrderStatus::Received);
});

it('destrabar la orden permite arrancar', function () {
    $o = orden();
    $o->block('Falta el filtro');
    $o->unblock();

    app(UpdateWorkOrderStatusAction::class)->execute($o->fresh(), WorkOrderStatus::Repairing, options: [
        'mechanic_id' => $this->mecanico->id,
    ]);

    expect($o->refresh()->status)->toBe(WorkOrderStatus::Repairing)
        ->and($o->isBlocked())->toBeFalse();
});

// ─── Plata ──────────────────────────────────────────────────────────────────

it('la orden gratis se entrega sin cobro y no queda como pendiente', function () {
    $o = orden(['status' => 'completed', 'total' => 0, 'work_performed' => 'Revisión sin cargo']);
    $this->actingAs($this->comercial);

    app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Delivered);

    $o->refresh();

    expect($o->isFree())->toBeTrue()
        ->and($o->payment_status)->toBe('paid')
        ->and($o->payments()->count())->toBe(0);
});

it('el adelanto y el saldo se suman como dos cobros con su fecha', function () {
    $o = orden(['status' => 'completed', 'total' => 100000, 'work_performed' => 'Listo']);

    Payment::create([
        'tenant_id' => $this->t->id, 'work_order_id' => $o->id, 'type' => 'adelanto',
        'amount' => 40000, 'method' => 'transferencia', 'paid_at' => now()->subDays(3),
    ]);

    expect($o->refresh()->payment_status)->toBe('partial')
        ->and($o->balance())->toEqual(60000.0);

    $this->actingAs($this->comercial);
    app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Delivered, options: [
        'payment' => ['amount' => 60000, 'method' => 'efectivo'],
    ]);

    $o->refresh();

    expect($o->payments()->count())->toBe(2)
        ->and($o->totalPaid())->toEqual(100000.0)
        ->and($o->payment_status)->toBe('paid')
        // Cada cobro conserva su fecha real, no la de la entrega.
        ->and($o->payments()->first()->paid_at->isYesterday())->toBeFalse();
});

// ─── Presupuestos: estados unificados ───────────────────────────────────────

it('los estados del presupuesto son pendiente, aprobado y rechazado', function () {
    expect(array_column(QuoteStatus::cases(), 'value'))->toBe(['pending', 'accepted', 'rejected'])
        ->and(QuoteStatus::Pending->getLabel())->toBe('Pendiente de aprobación');
});

it('los estados viejos siguen mostrándose sin romper', function () {
    expect(QuoteStatus::labelFor('draft'))->toBe('Borrador')
        ->and(QuoteStatus::labelFor('sent'))->toBe('Enviado')
        ->and(QuoteStatus::labelFor('pending'))->toBe('Pendiente de aprobación');
});

it('el presupuesto nuevo arranca pendiente de aprobación', function () {
    $q = Quote::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'mileage' => 50000,
        'status' => QuoteStatus::Pending,
    ]);

    expect($q->refresh()->status)->toBe(QuoteStatus::Pending);
});

// ─── El presupuesto define qué trabaja el mecánico ──────────────────────────

it('la orden hereda del presupuesto solo los puntos que necesitan trabajo', function () {
    $checklist = [
        ['id_punto' => 1, 'categoria' => 'Frenos',     'nombre_item' => 'Pastillas delanteras', 'estado' => 'MAL',     'aclaracion' => 'Gastadas'],
        ['id_punto' => 2, 'categoria' => 'Frenos',     'nombre_item' => 'Pastillas traseras',   'estado' => 'BIEN',    'aclaracion' => ''],
        ['id_punto' => 3, 'categoria' => 'Neumáticos', 'nombre_item' => 'Presión',              'estado' => 'REGULAR', 'aclaracion' => 'Baja'],
        ['id_punto' => 4, 'categoria' => 'Luces',      'nombre_item' => 'Bocina',               'estado' => 'BIEN',    'aclaracion' => ''],
    ];

    $trabajo = WorkOrder::buildChecklistFromQuote($checklist);

    // Solo los REGULAR y MAL: lo que está bien no se toca.
    expect($trabajo)->toHaveCount(2)
        ->and(collect($trabajo)->pluck('nombre_item')->all())->toBe(['Pastillas delanteras', 'Presión'])
        // El mecánico ve cómo lo encontró el presupuesto...
        ->and($trabajo[0]['estado_presupuesto'])->toBe('MAL')
        ->and($trabajo[0]['observacion_previa'])->toBe('Gastadas')
        // ...y arranca sin marcar si lo hizo.
        ->and($trabajo[0]['estado'])->toBeNull();
});

it('un presupuesto sin revisión genera una orden sin puntos a trabajar', function () {
    expect(WorkOrder::buildChecklistFromQuote(null))->toBe([])
        ->and(WorkOrder::buildChecklistFromQuote([]))->toBe([]);
});

it('la orden queda con novedades si algo no se pudo hacer', function () {
    $o = orden([
        'status'         => 'repairing',
        'work_performed' => 'Se cambiaron las pastillas',
        'checklist'      => [
            ['id_punto' => 1, 'nombre_item' => 'Pastillas', 'estado' => WorkOrder::PUNTO_HECHO, 'aclaracion' => ''],
            ['id_punto' => 2, 'nombre_item' => 'Presión',   'estado' => WorkOrder::PUNTO_NO_SE_PUDO, 'aclaracion' => 'Falta el compresor'],
        ],
    ]);

    expect($o->hasIssues())->toBeTrue()
        ->and($o->issuePoints())->toHaveCount(1);

    // Y se puede completar igual: el problema está explicado.
    app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Completed);

    expect($o->refresh()->status)->toBe(WorkOrderStatus::Completed);
});

it('no se completa si un punto quedó como "no se pudo" sin explicar', function () {
    $o = orden([
        'status'         => 'repairing',
        'work_performed' => 'Trabajo parcial',
        'checklist'      => [
            ['id_punto' => 1, 'nombre_item' => 'Presión', 'estado' => WorkOrder::PUNTO_NO_SE_PUDO, 'aclaracion' => ''],
        ],
    ]);

    expect(fn () => app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Completed))
        ->toThrow(DomainException::class);
});

it('las órdenes viejas con el checklist en formato anterior se pueden completar', function () {
    // Las 50 órdenes que ya existen en prod guardaron item/done/note, sin campo
    // estado. Si contaran como pendientes, no podrían cerrarse nunca.
    $o = orden([
        'status'         => 'repairing',
        'work_performed' => 'Service completo',
        'checklist'      => [
            ['item' => 'Luces y señales', 'done' => true, 'note' => null],
            ['item' => 'Frenos',          'done' => false, 'note' => null],
        ],
    ]);

    app(UpdateWorkOrderStatusAction::class)->execute($o, WorkOrderStatus::Completed);

    expect($o->refresh()->status)->toBe(WorkOrderStatus::Completed)
        ->and($o->hasIssues())->toBeFalse();
});

// ─── El kilometraje tiene que verse (lo pidió el usuario) ───────────────────

it('el PDF de la orden muestra el kilometraje y el trabajo realizado', function () {
    $o = orden([
        'status'         => 'completed',
        'mileage_in'     => 82500,
        'mileage_out'    => 82530,
        'work_performed' => 'Cambio de pastillas y purgado de frenos',
        'checklist'      => [
            ['id_punto' => 1, 'nombre_item' => 'Presión', 'estado' => WorkOrder::PUNTO_NO_SE_PUDO, 'aclaracion' => 'Falta el compresor'],
        ],
    ]);

    $html = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.work-order', ['workOrder' => $o->load(['tenant', 'customer', 'vehicle', 'items'])]);

    // El PDF se genera sin error y la ruta responde.
    expect($html)->not->toBeNull();

    $this->get(route('work-orders.pdf', $o))->assertOk();
});

it('la ficha de la orden muestra el kilometraje', function () {
    $o = orden(['mileage_in' => 82500]);

    $this->get(\App\Filament\Resources\WorkOrderResource::getUrl('view', ['record' => $o]))
        ->assertOk()
        ->assertSee('82.500');
});
