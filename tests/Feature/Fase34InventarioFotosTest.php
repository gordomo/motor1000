<?php

/**
 * Fase 3 y 4 de los pedidos del cliente:
 *  - 3: fotos del vehículo en la orden de trabajo
 *  - 5: los repuestos de la orden descuentan del inventario
 *  - 6: aviso de repuestos por debajo del mínimo
 *  - nuevo: tablero de órdenes cerradas por día / semana / mes
 */

use App\Actions\WorkOrder\UpdateWorkOrderStatusAction;
use App\Console\Commands\CheckLowStockCommand;
use App\Enums\WorkOrderStatus;
use App\Filament\Pages\WorkOrderClosuresReport;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Notifications\LowStockNotification;
use App\Services\Inventory\WorkOrderStockService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    \Spatie\Permission\Models\Role::findOrCreate('mechanic');

    $this->t = Tenant::factory()->create();
    $this->u = User::factory()->create(['tenant_id' => $this->t->id]);
    $this->u->assignRole('admin');
    app()->instance('current.tenant', $this->t);
    $this->actingAs($this->u);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'mileage' => 50000,
    ]);

    $this->tornillo = InventoryItem::create([
        'tenant_id' => $this->t->id, 'name' => 'Tornillo M8', 'unit' => 'un',
        'cost_price' => 100, 'sale_price' => 250,
        'stock_quantity' => 15, 'min_stock' => 5, 'is_active' => true,
    ]);

    $this->orden = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'repairing',
        'complaint' => 'Ruido', 'mileage_in' => 50000,
    ]);
});

/** Carga un ítem de repuesto vinculado al inventario. */
function itemRepuesto(WorkOrder $orden, int $inventoryItemId, float $cantidad): void
{
    $orden->items()->create([
        'type' => 'part', 'description' => 'Tornillo M8', 'quantity' => $cantidad,
        'unit_price' => 250, 'inventory_item_id' => $inventoryItemId,
    ]);
}

// ─── Pedido 5: descuento de stock ───────────────────────────────────────────

it('5: completar la orden descuenta los repuestos del inventario', function () {
    itemRepuesto($this->orden, $this->tornillo->id, 10);

    app(UpdateWorkOrderStatusAction::class)->execute($this->orden, WorkOrderStatus::Completed);

    // 15 - 10 = 5, como en el ejemplo del cliente.
    expect($this->tornillo->refresh()->stock_quantity)->toEqual(5.00)
        ->and(InventoryMovement::where('work_order_id', $this->orden->id)->where('type', 'out')->count())->toBe(1);
});

it('5: no descuenta dos veces si la orden vuelve a completarse', function () {
    itemRepuesto($this->orden, $this->tornillo->id, 10);

    $action = app(UpdateWorkOrderStatusAction::class);
    $action->execute($this->orden, WorkOrderStatus::Completed);
    $action->execute($this->orden->fresh(), WorkOrderStatus::Delivered);
    $action->execute($this->orden->fresh(), WorkOrderStatus::Completed);

    expect($this->tornillo->refresh()->stock_quantity)->toEqual(5.00);
});

it('5: reabrir la orden devuelve los repuestos al inventario', function () {
    itemRepuesto($this->orden, $this->tornillo->id, 10);

    $action = app(UpdateWorkOrderStatusAction::class);
    $action->execute($this->orden, WorkOrderStatus::Completed);
    expect($this->tornillo->refresh()->stock_quantity)->toEqual(5.00);

    $action->execute($this->orden->fresh(), WorkOrderStatus::Repairing);

    expect($this->tornillo->refresh()->stock_quantity)->toEqual(15.00)
        // El movimiento original no se borra: queda que salió y volvió.
        ->and(InventoryMovement::where('work_order_id', $this->orden->id)->count())->toBe(2);
});

it('5: la mano de obra no toca el inventario', function () {
    $this->orden->items()->create([
        'type' => 'labor', 'description' => 'Mano de obra', 'quantity' => 2, 'unit_price' => 30000,
    ]);

    app(UpdateWorkOrderStatusAction::class)->execute($this->orden, WorkOrderStatus::Completed);

    expect($this->tornillo->refresh()->stock_quantity)->toEqual(15.00)
        ->and(InventoryMovement::count())->toBe(0);
});

it('5: avisa cuando el stock no alcanza, pero deja cerrar la orden', function () {
    itemRepuesto($this->orden, $this->tornillo->id, 20); // hay 15

    $faltantes = app(WorkOrderStockService::class)->shortages($this->orden);

    expect($faltantes)->toHaveCount(1)
        ->and($faltantes[0]['disponible'])->toEqual(15.0)
        ->and($faltantes[0]['necesario'])->toEqual(20.0);

    app(UpdateWorkOrderStatusAction::class)->execute($this->orden, WorkOrderStatus::Completed);

    // Se descuenta igual: la pieza ya se usó, ocultarlo haría mentir al inventario.
    expect($this->tornillo->refresh()->stock_quantity)->toEqual(-5.00);
});

it('5: el tablero kanban también descuenta stock y no saltea la acción', function () {
    itemRepuesto($this->orden, $this->tornillo->id, 3);

    Livewire::test(\App\Filament\Pages\WorkOrdersBoard::class)
        ->call('moveOrder', $this->orden->id, 'completed');

    expect($this->tornillo->refresh()->stock_quantity)->toEqual(12.00)
        ->and($this->orden->refresh()->completed_at)->not->toBeNull();
});

it('arregla el total de la orden: los ítems "Otro" ya no quedan afuera', function () {
    $this->orden->items()->create(['type' => 'labor', 'description' => 'Mano de obra', 'quantity' => 1, 'unit_price' => 10000]);
    $this->orden->items()->create(['type' => 'part',  'description' => 'Filtro',       'quantity' => 1, 'unit_price' => 5000]);
    $this->orden->items()->create(['type' => 'other', 'description' => 'Grúa',         'quantity' => 1, 'unit_price' => 7000]);

    expect((float) $this->orden->refresh()->total)->toEqual(22000.0)
        ->and($this->orden->otherCost())->toEqual(7000.0);
});

// ─── Pedido 6: alerta de stock crítico ──────────────────────────────────────

it('6: avisa a los administradores qué repuestos están por debajo del mínimo', function () {
    Notification::fake();

    $this->tornillo->update(['stock_quantity' => 3]); // mínimo 5

    $this->artisan('stock:check')->assertSuccessful();

    Notification::assertSentTo($this->u, LowStockNotification::class);
});

it('6: no avisa si todo está por encima del mínimo', function () {
    Notification::fake();

    $this->artisan('stock:check')->assertSuccessful();

    Notification::assertNothingSent();
});

it('6: el mecánico no recibe el aviso de stock', function () {
    Notification::fake();

    $mecanico = User::factory()->create(['tenant_id' => $this->t->id]);
    $mecanico->assignRole('mechanic');
    $this->tornillo->update(['stock_quantity' => 1]);

    $this->artisan('stock:check')->assertSuccessful();

    Notification::assertNotSentTo($mecanico, LowStockNotification::class);
});

it('6: cada taller recibe solo sus propios faltantes', function () {
    Notification::fake();

    $otro = Tenant::factory()->create();
    $otroAdmin = User::factory()->create(['tenant_id' => $otro->id]);
    $otroAdmin->assignRole('admin');

    $this->tornillo->update(['stock_quantity' => 1]);

    $this->artisan('stock:check')->assertSuccessful();

    Notification::assertSentTo($this->u, LowStockNotification::class);
    Notification::assertNotSentTo($otroAdmin, LowStockNotification::class);
});

// ─── Pedido nuevo: tablero de cierres ───────────────────────────────────────

it('nuevo: cuenta las órdenes cerradas por día, semana y mes', function () {
    // 2 cerradas hoy, 1 hace 10 días.
    foreach ([now(), now(), now()->subDays(10)] as $fecha) {
        WorkOrder::create([
            'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id, 'status' => 'completed',
            'complaint' => 'Service', 'mileage_in' => 50000, 'completed_at' => $fecha,
        ]);
    }

    $page = Livewire::test(WorkOrderClosuresReport::class)
        ->set('desde', now()->subMonth()->toDateString())
        ->set('hasta', now()->toDateString());

    $resumen = $page->instance()->resumen;

    expect($resumen['hoy'])->toBe(2)
        ->and($resumen['total'])->toBe(3);
});

it('nuevo: una orden sin cerrar no cuenta', function () {
    // $this->orden está en 'repairing', sin completed_at.
    $resumen = Livewire::test(WorkOrderClosuresReport::class)->instance()->resumen;

    expect($resumen['total'])->toBe(0);
});

it('nuevo: el tablero exporta a PDF y a Excel', function () {
    WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id,
        'vehicle_id' => $this->vehicle->id, 'status' => 'completed',
        'complaint' => 'Service', 'mileage_in' => 50000, 'completed_at' => now(),
    ]);

    Livewire::test(WorkOrderClosuresReport::class)
        ->callAction('pdf')
        ->assertHasNoActionErrors();

    Livewire::test(WorkOrderClosuresReport::class)
        ->callAction('excel')
        ->assertHasNoActionErrors();
});

it('nuevo: el mecánico no accede al tablero de cierres', function () {
    $mecanico = User::factory()->create(['tenant_id' => $this->t->id]);
    $mecanico->assignRole('mechanic');
    $this->actingAs($mecanico);

    expect(WorkOrderClosuresReport::canAccess())->toBeFalse();
});

// ─── Pedido 3: fotos ────────────────────────────────────────────────────────

it('3: la orden guarda fotos de ingreso y de entrega por separado', function () {
    $this->orden->update([
        'photos_in'  => ['work-orders/ingreso/frente.jpg', 'work-orders/ingreso/lateral.jpg'],
        'photos_out' => ['work-orders/entrega/frente.jpg'],
    ]);

    $this->orden->refresh();

    expect($this->orden->photos_in)->toHaveCount(2)
        ->and($this->orden->photos_out)->toHaveCount(1);
});

it('3: una orden sin fotos no rompe nada', function () {
    expect($this->orden->photos_in)->toBeNull();

    $this->get(route('work-orders.pdf', $this->orden))->assertOk();
});
