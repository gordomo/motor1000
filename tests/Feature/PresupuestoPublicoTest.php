<?php

/**
 * El presupuesto que se manda por WhatsApp tiene que abrir sin login: el link
 * viejo (/quotes/{id}/pdf/stream) exigía usuario del taller y al cliente le daba error.
 */

use App\Filament\Resources\QuoteResource\Pages\ListQuotes;
use App\Http\Controllers\QuotePdfController;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->t = Tenant::factory()->create();
    app()->instance('current.tenant', $this->t);

    $nuevo = function () {
        $c = Customer::factory()->create(['tenant_id' => $this->t->id, 'whatsapp' => '+54 9 223 555-1234']);
        $v = Vehicle::factory()->create(['tenant_id' => $this->t->id, 'customer_id' => $c->id]);

        return Quote::create([
            'tenant_id' => $this->t->id, 'customer_id' => $c->id, 'vehicle_id' => $v->id,
            'status' => 'pending', 'type' => 'sin_checklist',
        ]);
    };
    $this->quote = $nuevo();
    $this->otro  = $nuevo();

    // El cliente que abre el link no tiene sesión ni taller.
    app()->forgetInstance('current.tenant');
});

it('el cliente abre el presupuesto desde el link firmado, sin login', function () {
    $this->get(QuotePdfController::linkPublico($this->quote))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('sin firma o con la firma de otro presupuesto, no se abre', function () {
    $this->get(route('public.quotes.pdf', ['quoteId' => $this->quote->id]))->assertForbidden();

    // Cambiar el número en un link válido no da acceso a otro presupuesto.
    $ajeno = str_replace(
        '/presupuesto/' . $this->quote->id . '?',
        '/presupuesto/' . $this->otro->id . '?',
        QuotePdfController::linkPublico($this->quote),
    );
    $this->get($ajeno)->assertForbidden();
});

it('lo abre aunque el navegador tenga sesión de otro taller', function () {
    $otroTaller = Tenant::factory()->create();
    $this->actingAs(User::factory()->create(['tenant_id' => $otroTaller->id]));

    $this->get(QuotePdfController::linkPublico($this->quote))->assertOk();
});

it('el botón de WhatsApp manda el link firmado y no el que pide login', function () {
    app()->instance('current.tenant', $this->t);
    $this->actingAs(User::factory()->create(['tenant_id' => $this->t->id]));
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $url = Livewire::test(ListQuotes::class)
        ->instance()
        ->getTable()
        ->getAction('whatsapp')
        ->record($this->quote)
        ->getUrl();

    $mensaje = urldecode(parse_url($url, PHP_URL_QUERY));

    expect($url)->toStartWith('https://wa.me/5492235551234?')
        ->and($mensaje)->toContain('/presupuesto/' . $this->quote->id . '?signature=')
        ->and($mensaje)->not->toContain('/pdf/stream');
});
