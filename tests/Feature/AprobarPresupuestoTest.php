<?php

/**
 * Pasar de presupuesto a orden: "Aprobar total" o "Aprobar parcial" (el
 * cliente muchas veces no acepta todo). La orden sale con lo aprobado y el
 * presupuesto conserva lo cotizado, marcando qué se aprobó.
 */

use App\Enums\QuoteStatus;
use App\Filament\Resources\QuoteResource;
use App\Filament\Resources\QuoteResource\Pages\ListQuotes;
use App\Filament\Resources\QuoteResource\Pages\ViewQuote;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create();
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $u = User::factory()->create(['tenant_id' => $this->t->id]);
    $u->assignRole('receptionist');
    $this->actingAs($u);

    $c = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $v = Vehicle::factory()->create(['tenant_id' => $this->t->id, 'customer_id' => $c->id]);

    $this->quote = Quote::create([
        'tenant_id' => $this->t->id, 'customer_id' => $c->id, 'vehicle_id' => $v->id,
        'status' => 'pending', 'type' => 'sin_checklist', 'mileage' => 90000,
        'items' => [
            ['tipo' => 'mano_de_obra', 'descripcion' => 'Cambio de pastillas', 'cantidad' => 1, 'precio_unitario' => 40000, 'total' => 40000],
            ['tipo' => 'repuesto', 'descripcion' => 'Pastillas', 'cantidad' => 1, 'precio_unitario' => 60000, 'total' => 60000],
            ['tipo' => 'repuesto', 'descripcion' => 'Discos', 'cantidad' => 2, 'precio_unitario' => 50000, 'total' => 100000],
        ],
        'subtotal' => 200000, 'discount' => 20000, 'total' => 180000,
    ]);
});

it('aprobar total genera la orden con todo y lleva a la orden', function () {
    Livewire::test(ViewQuote::class, ['record' => $this->quote->id])
        ->callAction('aprobar_total', data: [])
        ->assertHasNoActionErrors()
        ->assertRedirect();

    $wo = WorkOrder::where('quote_id', $this->quote->id)->sole();
    $q = $this->quote->fresh();

    expect($wo->items()->count())->toBe(3)
        ->and((float) $wo->discount)->toBe(20000.0)
        ->and($q->status)->toBe(QuoteStatus::Accepted)
        ->and($q->esAprobacionParcial())->toBeFalse()
        ->and(collect($q->items)->pluck('aprobado')->all())->toBe([true, true, true]);
});

it('aprobar parcial: la orden sale solo con lo tildado y el presupuesto guarda todo', function () {
    // Abre con todo tildado y el descuento del presupuesto.
    Livewire::test(ViewQuote::class, ['record' => $this->quote->id])
        ->mountAction('aprobar_parcial')
        ->assertActionDataSet(['aprobados' => [0, 1, 2], 'discount' => 20000.0]);

    // Se setea la lista entera: setActionData la aplana (Arr::dot) y escribe
    // posición por posición, así que no podría destildar el tercer ítem.
    Livewire::test(ViewQuote::class, ['record' => $this->quote->id])
        ->mountAction('aprobar_parcial')
        ->set('mountedActionsData.0.aprobados', [0, 1])
        ->set('mountedActionsData.0.discount', 5000)
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $wo = WorkOrder::where('quote_id', $this->quote->id)->sole();
    $q = $this->quote->fresh();

    expect($wo->items()->pluck('description')->all())->toBe(['Cambio de pastillas', 'Pastillas'])
        ->and((float) $wo->discount)->toBe(5000.0)
        ->and(count($q->items))->toBe(3)
        ->and($q->esAprobacionParcial())->toBeTrue()
        ->and($q->subtotalAprobado())->toBe(100000.0)
        ->and(collect($q->itemsNoAprobados())->pluck('descripcion')->all())->toBe(['Discos']);

    // En el listado se ve como "Aprobado parcial".
    Livewire::test(ListQuotes::class)->assertSee('Aprobado parcial');
});

it('aprobar parcial pide al menos un ítem', function () {
    Livewire::test(ViewQuote::class, ['record' => $this->quote->id])
        ->callAction('aprobar_parcial', data: ['aprobados' => []])
        ->assertHasActionErrors(['aprobados' => 'required']);

    expect(WorkOrder::count())->toBe(0);
});

it('con la orden generada no se puede aprobar de nuevo ni editar el presupuesto', function () {
    Livewire::test(ViewQuote::class, ['record' => $this->quote->id])->callAction('aprobar_total', data: []);

    Livewire::test(ViewQuote::class, ['record' => $this->quote->id])
        ->assertActionHidden('aprobar_total')
        ->assertActionHidden('aprobar_parcial');

    expect(QuoteResource::canEdit($this->quote->fresh()))->toBeFalse();
    $this->get(QuoteResource::getUrl('edit', ['record' => $this->quote]))->assertForbidden();
});

it('un presupuesto rechazado no se aprueba', function () {
    $this->quote->update(['status' => 'rejected']);

    Livewire::test(ListQuotes::class)
        ->assertTableActionHidden('aprobar_total', $this->quote)
        ->assertTableActionHidden('aprobar_parcial', $this->quote);
});
