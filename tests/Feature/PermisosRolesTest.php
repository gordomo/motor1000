<?php

/**
 * Permisos definidos por el cliente (2026-10), probados entrando por URL:
 * - Comercial: Órdenes, Citas y calendario, Clientes y vehículos, Presupuestos,
 *   Plantillas, Para hoy y los números. El resto es del administrador.
 * - Mecánico: solo su tablero. No ve precios: ni órdenes ni PDFs por URL.
 */

use App\Filament\Pages\AppointmentsCalendar;
use App\Filament\Pages\ParaHoyPage;
use App\Filament\Resources\CommunicationTemplateResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InspectionResource;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\MechanicResource;
use App\Filament\Resources\QuoteResource;
use App\Filament\Resources\ReminderResource;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\VehicleResource;
use App\Filament\Resources\WorkOrderResource;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Filament\Facades\Filament;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create();
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $this->customer = Customer::factory()->create(['tenant_id' => $this->t->id]);
    $vehicle = Vehicle::factory()->create(['tenant_id' => $this->t->id, 'customer_id' => $this->customer->id]);
    $this->orden = WorkOrder::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'vehicle_id' => $vehicle->id,
        'status' => 'received', 'complaint' => 'x', 'mileage_in' => 1, 'labor_cost' => 98765,
    ]);
    $this->quote = Quote::create([
        'tenant_id' => $this->t->id, 'customer_id' => $this->customer->id, 'vehicle_id' => $vehicle->id,
        'status' => 'pending', 'type' => 'sin_checklist',
    ]);

    $this->como = function (string $rol): void {
        $u = User::factory()->create(['tenant_id' => $this->t->id]);
        $u->assignRole($rol);
        $this->actingAs($u);
    };
});

it('el comercial entra a lo suyo', function () {
    ($this->como)('receptionist');

    foreach ([
        WorkOrderResource::getUrl('index'),
        WorkOrderResource::getUrl('view', ['record' => $this->orden]),
        QuoteResource::getUrl('index'),
        CustomerResource::getUrl('view', ['record' => $this->customer]),
        VehicleResource::getUrl('index'),
        AppointmentsCalendar::getUrl(),
        CommunicationTemplateResource::getUrl('index'),
        ParaHoyPage::getUrl(),
        route('quotes.pdf', $this->quote),
        route('work-orders.pdf', $this->orden),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('el comercial no entra a lo del administrador', function () {
    ($this->como)('receptionist');

    foreach ([InvoiceResource::class, InventoryItemResource::class, InspectionResource::class,
              ReminderResource::class, TaskResource::class, MechanicResource::class] as $recurso) {
        $this->get($recurso::getUrl('index'))->assertForbidden();
    }

    // En la ficha del cliente tampoco aparece la pestaña de Revisiones.
    $this->get(CustomerResource::getUrl('view', ['record' => $this->customer]))
        ->assertOk()
        ->assertDontSee('Revisiones');
});

it('el mecánico no ve órdenes ni PDFs con precios, ni entrando por URL', function () {
    ($this->como)('mechanic');

    $this->get(WorkOrderResource::getUrl('index'))->assertForbidden();
    $this->get(WorkOrderResource::getUrl('view', ['record' => $this->orden]))->assertForbidden();
    $this->get(WorkOrderResource::getUrl('edit', ['record' => $this->orden]))->assertForbidden();
    $this->get(route('work-orders.pdf', $this->orden))->assertForbidden();
    $this->get(route('quotes.pdf', $this->quote))->assertForbidden();
    $this->get(route('quotes.pdf.stream', $this->quote))->assertForbidden();
});

it('el administrador sigue viendo todo', function () {
    ($this->como)('admin');

    foreach ([InvoiceResource::class, InventoryItemResource::class, InspectionResource::class,
              ReminderResource::class, TaskResource::class, MechanicResource::class,
              WorkOrderResource::class, QuoteResource::class] as $recurso) {
        $this->get($recurso::getUrl('index'))->assertOk();
    }

    $this->get(CustomerResource::getUrl('view', ['record' => $this->customer]))->assertSee('Revisiones');
});
