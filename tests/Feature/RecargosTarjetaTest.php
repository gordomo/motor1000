<?php

/**
 * Formas de pago con recargo (definido con el cliente):
 * presupuesto de $1.000.000; tarjeta = +10% de facturación y, en crédito,
 * + el costo financiero de las cuotas (3 cuotas: 15%), uno sobre otro:
 * 1.000.000 × 1,10 × 1,15 = 1.265.000 → 3 cuotas de 421.666,67.
 * El porcentaje está configurado (no se escribe a mano) y el sistema calcula.
 */

use App\Filament\Pages\RecargosTarjeta;
use App\Filament\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Support\Recargos;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create(['settings' => ['otra_cosa' => 'se conserva']]);
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Recargos::guardar($this->t, [
        'facturacion'             => 10,
        'metodos_con_facturacion' => ['debito', 'credito'],
        'cuotas'                  => [['cuotas' => 1, 'porcentaje' => 0], ['cuotas' => 3, 'porcentaje' => 15], ['cuotas' => 6, 'porcentaje' => 30]],
    ]);

    $this->comercial = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->comercial->assignRole('receptionist');
    $this->actingAs($this->comercial);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->vehicle = Vehicle::factory()->create(['tenant_id' => $this->t->id, 'customer_id' => $this->customer->id]);
});

it('calcula el ejemplo del cliente: uno sobre otro', function () {
    expect(Recargos::calcular(1_000_000, 'efectivo'))->toMatchArray(['total' => 1_000_000.0, 'recargo' => 0.0])
        ->and(Recargos::calcular(1_000_000, 'debito'))->toMatchArray(['total' => 1_100_000.0, 'recargo' => 100_000.0])
        ->and(Recargos::calcular(1_000_000, 'credito', 3))->toMatchArray(['total' => 1_265_000.0, 'recargo' => 265_000.0, 'cuotas' => 3, 'cuota' => 421_666.67])
        ->and(Recargos::calcular(1_000_000, 'mercadopago'))->toMatchArray(['total' => 1_000_000.0])
        ->and(Recargos::describir(1_000_000, 'credito', 3))->toBe('Tarjeta de crédito: 3 cuotas de $421.667 (total $1.265.000)');
});

it('guardar los recargos no pisa el resto de la configuración del taller', function () {
    expect($this->t->fresh()->settings['otra_cosa'])->toBe('se conserva');
});

it('el comercial configura los porcentajes; el mecánico no', function () {
    Livewire::test(RecargosTarjeta::class)
        ->assertFormSet(['facturacion' => 10.0])
        ->fillForm(['facturacion' => 12])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Recargos::config($this->t->fresh())['facturacion'])->toBe(12.0);

    $mecanico = User::factory()->create(['tenant_id' => $this->t->id]);
    $mecanico->assignRole('mechanic');
    $this->actingAs($mecanico);
    expect(RecargosTarjeta::canAccess())->toBeFalse();
});

it('el presupuesto guarda la forma de pago elegida con su recargo, y el PDF la muestra', function () {
    $q = Quote::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
        'status' => 'pending', 'type' => 'sin_checklist',
        'items' => [['tipo' => 'mano_de_obra', 'descripcion' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 1_000_000]],
        'payment_method' => 'credito', 'installments' => 3,
    ]);

    expect((float) $q->surcharge)->toBe(265_000.0)
        ->and($q->totalConRecargo())->toBe(1_265_000.0);

    $this->get(route('quotes.pdf.stream', $q))->assertOk();
    $html = view('pdf.quote', ['quote' => $q->load(['tenant', 'customer', 'vehicle'])])->render();
    expect($html)->toContain('Tarjeta de crédito en 3 cuotas de $ 421.666,67')->toContain('1.265.000,00');

    // Si pasa a efectivo, no hay recargo ni cuotas.
    $q->update(['payment_method' => 'efectivo']);
    expect((float) $q->fresh()->surcharge)->toBe(0.0)->and($q->fresh()->installments)->toBeNull();
});

it('cobrar con tarjeta en cuotas suma el recargo solo y la orden queda saldada', function () {
    $q = Quote::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
        'status' => 'accepted', 'type' => 'sin_checklist', 'payment_method' => 'credito', 'installments' => 3,
        'items' => [['tipo' => 'mano_de_obra', 'descripcion' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 1_000_000]],
    ]);
    $orden = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
        'quote_id' => $q->id, 'status' => 'delivered', 'complaint' => 'x', 'mileage_in' => 1,
        'total' => 1_000_000, 'delivered_at' => now(),
    ]);

    // El formulario propone la forma de pago del presupuesto.
    Livewire::test(ListWorkOrders::class)
        ->mountTableAction('registrar_cobro', $orden)
        ->assertTableActionDataSet(['amount' => 1_000_000.0, 'method' => 'credito', 'installments' => 3])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $cobro = $orden->payments()->sole();
    $orden->refresh();

    expect((float) $cobro->amount)->toBe(1_265_000.0)
        ->and((float) $cobro->surcharge)->toBe(265_000.0)
        ->and($cobro->installments)->toBe(3)
        ->and($cobro->methodLabel())->toBe('Tarjeta de crédito (3 cuotas)')
        ->and($orden->balance())->toBe(0.0)
        ->and($orden->payment_status)->toBe('paid');
});

it('en efectivo no hay recargo', function () {
    $orden = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
        'status' => 'delivered', 'complaint' => 'x', 'mileage_in' => 1, 'total' => 500_000, 'delivered_at' => now(),
    ]);

    Livewire::test(ListWorkOrders::class)
        ->callTableAction('registrar_cobro', $orden, data: ['amount' => 200_000, 'method' => 'efectivo'])
        ->assertHasNoTableActionErrors();

    expect((float) $orden->payments()->sole()->amount)->toBe(200_000.0)
        ->and($orden->fresh()->balance())->toBe(300_000.0)
        ->and($orden->fresh()->payment_status)->toBe('partial');
});
