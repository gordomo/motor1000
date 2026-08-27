<?php

/**
 * Fase 2 de los pedidos del cliente:
 *  - 2: dos tipos de presupuesto (con y sin checklist obligatorio)
 *  - 2/17: los puntos del checklist los configura cada taller
 *  - 17: módulo de Revisiones (checklist + notas, sin precios, exportable a PDF)
 */

use App\Enums\QuoteType;
use App\Filament\Resources\ChecklistItemResource\Pages\ListChecklistItems;
use App\Filament\Resources\InspectionResource\Pages\CreateInspection;
use App\Filament\Resources\QuoteResource\Pages\CreateQuote;
use App\Models\ChecklistItem;
use App\Models\Customer;
use App\Models\Inspection;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    \Spatie\Permission\Models\Role::findOrCreate('receptionist');

    $this->t = Tenant::factory()->create();
    $this->u = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->u->assignRole('admin');
    app()->instance('current.tenant', $this->t);
    $this->actingAs($this->u);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $this->customer->id,
        'mileage'     => 60000,
    ]);

    // La migración siembra el catálogo por taller; los tenants creados por factory
    // después de migrar no lo tienen, así que lo sembramos igual que la migración.
    foreach (ChecklistItem::DEFAULT_CATALOG as $i => $item) {
        ChecklistItem::create([
            'tenant_id'   => $this->t->id,
            'categoria'   => $item['categoria'],
            'nombre_item' => $item['nombre_item'],
            'sort_order'  => $i + 1,
        ]);
    }
});

// ─── Pedido 2: checklist configurable ───────────────────────────────────────

it('2: el checklist sale de los puntos configurados por el taller', function () {
    ChecklistItem::create([
        'tenant_id'   => $this->t->id,
        'categoria'   => 'Aire acondicionado',
        'nombre_item' => 'Carga de gas',
    ]);

    $checklist = Quote::defaultChecklist();

    expect($checklist)->toHaveCount(21)
        ->and(collect($checklist)->pluck('nombre_item'))->toContain('Carga de gas');
});

it('2: un punto desactivado no aparece en los presupuestos nuevos', function () {
    ChecklistItem::query()->where('nombre_item', 'Bocina')->update(['is_active' => false]);

    expect(collect(Quote::defaultChecklist())->pluck('nombre_item'))
        ->not->toContain('Bocina')
        ->and(Quote::defaultChecklist())->toHaveCount(19);
});

it('2: cada taller ve solo sus propios puntos', function () {
    $otro = Tenant::factory()->create();
    ChecklistItem::create([
        'tenant_id'   => $otro->id,
        'categoria'   => 'Otro taller',
        'nombre_item' => 'Punto ajeno',
    ]);

    expect(collect(Quote::defaultChecklist())->pluck('nombre_item'))
        ->not->toContain('Punto ajeno');
});

it('2: editar los puntos no altera los presupuestos ya emitidos', function () {
    $quote = Quote::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'mileage' => 60000, 'status' => 'pending',
    ]);
    $original = $quote->checklist;

    ChecklistItem::query()->delete();

    expect($quote->refresh()->checklist)->toBe($original)
        ->and($original)->toHaveCount(20);
});

it('2: solo el administrador configura los puntos de revisión', function () {
    Livewire::test(ListChecklistItems::class)->assertOk();

    $reception = User::factory()->create(['tenant_id' => $this->t->id]);
    $reception->assignRole('receptionist');
    $this->actingAs($reception);

    expect(\App\Filament\Resources\ChecklistItemResource::canViewAny())->toBeFalse();
});

// ─── Pedido 2: los dos tipos de presupuesto ─────────────────────────────────

it('2: el presupuesto sin revisión se guarda sin completar el checklist', function () {
    Livewire::test(CreateQuote::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'vehicle_id'  => $this->vehicle->id,
            'mileage'     => 61000,
            'type'        => QuoteType::SinChecklist->value,
            'status'      => 'pending',
            'items'       => [
                ['tipo' => 'mano_de_obra', 'descripcion' => 'Cambio de aceite', 'cantidad' => 1, 'precio_unitario' => 45000],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::first();

    expect($quote)->not->toBeNull()
        ->and($quote->type)->toBe(QuoteType::SinChecklist)
        // No arrastra 20 puntos vacíos que después ensucien el PDF.
        ->and($quote->checklist)->toBeEmpty();
});

it('2: el presupuesto con revisión sigue exigiendo el checklist completo', function () {
    Livewire::test(CreateQuote::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'vehicle_id'  => $this->vehicle->id,
            'mileage'     => 61000,
            'type'        => QuoteType::ConChecklist->value,
            'status'      => 'pending',
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Quote::count())->toBe(0);
});

it('2: por defecto el presupuesto es con revisión', function () {
    $quote = Quote::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'mileage' => 60000, 'status' => 'pending',
    ]);

    expect($quote->refresh()->type)->toBe(QuoteType::ConChecklist)
        ->and($quote->checklist)->toHaveCount(20);
});

// ─── Pedido 17: módulo de revisiones ────────────────────────────────────────

it('17: crea una revisión con el checklist del taller y sin precios', function () {
    Livewire::test(CreateInspection::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'vehicle_id'  => $this->vehicle->id,
            'mileage'     => 62000,
            'notes'       => 'Revisión de rutina.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $inspection = Inspection::first();

    expect($inspection)->not->toBeNull()
        ->and($inspection->code)->toBe('REV-00001')
        ->and($inspection->checklist)->toHaveCount(20)
        ->and($inspection->notes)->toBe('Revisión de rutina.')
        // El KM de la revisión también actualiza el del vehículo.
        ->and($this->vehicle->refresh()->mileage)->toBe(62000);
});

it('17: la revisión se puede guardar a medio completar', function () {
    $inspection = Inspection::create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $this->customer->id,
        'vehicle_id'  => $this->vehicle->id,
        'checklist'   => [
            ['id_punto' => 1, 'categoria' => 'Frenos', 'nombre_item' => 'Pastillas', 'estado' => 'BIEN', 'aclaracion' => ''],
            ['id_punto' => 2, 'categoria' => 'Luces',  'nombre_item' => 'Bocina',    'estado' => null,   'aclaracion' => ''],
        ],
    ]);

    expect($inspection->checkedItems())->toHaveCount(1);
});

it('17: numera las revisiones de forma correlativa por taller', function () {
    $make = fn () => Inspection::create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $this->customer->id,
        'vehicle_id'  => $this->vehicle->id,
    ]);

    expect($make()->code)->toBe('REV-00001')
        ->and($make()->code)->toBe('REV-00002');
});

it('17: exporta la revisión a PDF e imprime', function () {
    $inspection = Inspection::create([
        'tenant_id'   => $this->t->id,
        'customer_id' => $this->customer->id,
        'vehicle_id'  => $this->vehicle->id,
        'mileage'     => 60000,
        'notes'       => 'Cambiar pastillas en la próxima visita.',
    ]);

    $this->get(route('inspections.pdf', $inspection))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->get(route('inspections.pdf.stream', $inspection))->assertOk();
});

it('17: no se puede ver el PDF de una revisión de otro taller', function () {
    $otro = Tenant::factory()->create();
    $ajeno = Customer::factory()->create(['tenant_id' => $otro->id]);
    $auto  = Vehicle::factory()->create(['tenant_id' => $otro->id, 'customer_id' => $ajeno->id]);

    $inspection = Inspection::create([
        'tenant_id'   => $otro->id,
        'customer_id' => $ajeno->id,
        'vehicle_id'  => $auto->id,
    ]);

    // 404 y no 403: el scope de taller filtra el binding de la ruta, así que la
    // revisión ajena ni siquiera se resuelve. Mejor todavía, porque no revela
    // que exista.
    $this->get(route('inspections.pdf', $inspection))->assertNotFound();
});
