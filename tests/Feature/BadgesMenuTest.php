<?php

/**
 * Los badges del menú: qué cuentan y qué explican.
 *
 * El de Inventario contaba con un criterio distinto al del widget y al del aviso
 * diario: como el stock mínimo viene en cero, un repuesto con stock cero
 * encendía el rojo pero después no aparecía en ningún lado.
 */

use App\Console\Commands\CheckLowStockCommand;
use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\QuoteResource;
use App\Filament\Resources\ReminderResource;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\WorkOrderResource;
use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\User;
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
    $this->actingAs($this->admin);
});

/** Repuesto con stock y mínimo a medida. */
function repuesto(string $nombre, float $stock, float $minimo, bool $activo = true): InventoryItem
{
    return InventoryItem::create([
        'tenant_id' => test()->t->id, 'name' => $nombre, 'unit' => 'un',
        'cost_price' => 100, 'sale_price' => 200,
        'stock_quantity' => $stock, 'min_stock' => $minimo, 'is_active' => $activo,
    ]);
}

it('un repuesto sin mínimo definido no enciende el badge aunque esté en cero', function () {
    // El caso real de prod: "Evolution 10w40", stock 0, mínimo 0.
    repuesto('Evolution 10w40', 0, 0);

    expect(InventoryItemResource::getNavigationBadge())->toBeNull();
});

it('un repuesto que llegó a su mínimo sí lo enciende', function () {
    repuesto('Filtro de aceite', 2, 5);

    expect(InventoryItemResource::getNavigationBadge())->toBe('1');
});

it('un repuesto inactivo no cuenta', function () {
    repuesto('Repuesto viejo', 0, 3, activo: false);

    expect(InventoryItemResource::getNavigationBadge())->toBeNull();
});

it('el badge, el widget y el aviso diario cuentan exactamente lo mismo', function () {
    repuesto('Sin mínimo', 0, 0);            // no cuenta en ningún lado
    repuesto('Inactivo', 0, 9, activo: false); // tampoco
    repuesto('Bajo', 1, 4);                  // este sí
    repuesto('De sobra', 50, 4);             // no

    $enBadge = (int) InventoryItemResource::getNavigationBadge();
    $enScope = InventoryItem::query()->belowMinimum()->pluck('name')->all();

    expect($enBadge)->toBe(1)
        ->and($enScope)->toBe(['Bajo']);
});

it('isLowStock coincide con el criterio del scope', function () {
    expect(repuesto('Sin mínimo', 0, 0)->isLowStock())->toBeFalse()
        ->and(repuesto('Bajo', 1, 4)->isLowStock())->toBeTrue()
        ->and(repuesto('Inactivo', 0, 9, activo: false)->isLowStock())->toBeFalse();
});

it('todos los badges del menú explican qué cuentan', function () {
    $recursos = [
        WorkOrderResource::class,
        QuoteResource::class,
        AppointmentResource::class,
        InventoryItemResource::class,
        InvoiceResource::class,
        ReminderResource::class,
        TaskResource::class,
    ];

    foreach ($recursos as $recurso) {
        expect($recurso::getNavigationBadgeTooltip())
            ->toBeString()
            ->not->toBeEmpty();
    }
});
