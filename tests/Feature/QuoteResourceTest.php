<?php

use App\Filament\Resources\QuoteResource\Pages\CreateQuote;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->tenant   = Tenant::factory()->create();
    $this->user     = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->vehicle  = Vehicle::factory()->create([
        'tenant_id'   => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    app()->instance('current.tenant', $this->tenant);
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

// Regresión falla #2: la página de crear presupuesto devolvía 500 por el
// closure orderBy en el select de cliente (qualifyColumn() on null).
it('renders the create quote page without a server error', function () {
    Livewire::test(CreateQuote::class)->assertOk();
});

// El checklist de 20 puntos debe estar disponible (precargado) en el alta.
it('preloads the 20-point checklist on the create form', function () {
    Livewire::test(CreateQuote::class)
        ->assertFormFieldExists('checklist')
        ->assertFormSet(fn (array $state): bool => count($state['checklist'] ?? []) === 20);
});
