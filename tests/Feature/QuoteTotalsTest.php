<?php

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\Vehicle;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Regresión: los totales son campos disabled en el form (no se persisten),
// así que se calculan en el modelo al guardar. Sin esto, el PDF mostraba $0.
it('calcula los totales del presupuesto al guardar', function () {
    $t = Tenant::factory()->create();
    $c = Customer::factory()->create(['tenant_id' => $t->id]);
    $v = Vehicle::factory()->create(['tenant_id' => $t->id, 'customer_id' => $c->id]);
    app()->instance('current.tenant', $t);

    $q = Quote::create([
        'tenant_id'   => $t->id,
        'customer_id' => $c->id,
        'vehicle_id'  => $v->id,
        'status'      => 'draft',
        'tax'         => 0,
        'discount'    => 10000,
        // items como los manda el form (con el campo 'total' disabled → sin total)
        'items' => [
            ['tipo' => 'mano_de_obra', 'descripcion' => 'cambio distrib', 'cantidad' => 1, 'precio_unitario' => 290000],
            ['tipo' => 'repuesto', 'descripcion' => 'kit', 'cantidad' => 1, 'precio_unitario' => 2000000],
        ],
    ]);

    $q->refresh();

    expect((float) $q->subtotal)->toBe(2290000.0)
        ->and((float) $q->total)->toBe(2280000.0) // 2290000 + 0 - 10000
        ->and((float) $q->items[0]['total'])->toBe(290000.0)
        ->and((float) $q->items[1]['total'])->toBe(2000000.0);
});
