<?php

/**
 * Los textos de los mensajes se editan en Plantillas de comunicación. Sin
 * plantilla activa se usa el texto de siempre. El comercial puede editarlas.
 */

use App\Filament\Resources\CommunicationTemplateResource\Pages\CreateCommunicationTemplate;
use App\Filament\Resources\CommunicationTemplateResource\Pages\ListCommunicationTemplates;
use App\Models\CommunicationTemplate;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Mensajes;
use App\Support\ParaHoy;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'receptionist', 'mechanic'] as $rol) {
        \Spatie\Permission\Models\Role::findOrCreate($rol);
    }

    $this->t = Tenant::factory()->create(['name' => '341 Boxes']);
    app()->instance('current.tenant', $this->t);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    // El comercial es quien edita los textos.
    $u = User::factory()->create(['tenant_id' => $this->t->id]);
    $u->assignRole('receptionist');
    $this->actingAs($u);

    $this->cliente = Customer::factory()->create(['tenant_id' => $this->t->id, 'name' => 'Ana Gómez', 'birthday' => '1990-10-02']);
});

it('sin plantilla usa el texto de siempre, y limpia lo que quedó vacío', function () {
    expect(ParaHoy::mensajeCumpleanos($this->cliente))->toBe('¡Hola Ana! Desde 341 Boxes te deseamos un muy feliz cumpleaños. ¡Que lo pases genial!')
        ->and(Mensajes::cuerpo('recordatorio', 'whatsapp', ['nombre' => 'Ana', 'taller' => 'X', 'recordatorio' => 'Cambio de aceite', 'patente' => null]))
        ->toBe('Hola Ana, te escribimos de X. Te recordamos: Cambio de aceite. ¿Querés que te reservemos un turno?');
});

it('con una plantilla activa usa ese texto; desactivada vuelve al de siempre', function () {
    $p = CommunicationTemplate::create([
        'tenant_id' => $this->t->id, 'name' => 'Cumple', 'slug' => 'cumple-x', 'channel' => 'whatsapp',
        'event' => 'cumpleanos', 'body' => 'Feliz cumple {nombre}, te regalamos el lavado. {taller}', 'is_active' => true,
    ]);

    expect(ParaHoy::mensajeCumpleanos($this->cliente))->toBe('Feliz cumple Ana, te regalamos el lavado. 341 Boxes');

    $p->update(['is_active' => false]);
    expect(ParaHoy::mensajeCumpleanos($this->cliente))->toStartWith('¡Hola Ana! Desde 341 Boxes');
});

it('la plantilla de otro taller no se usa', function () {
    $otro = Tenant::factory()->create();
    CommunicationTemplate::withoutGlobalScopes()->create([
        'tenant_id' => $otro->id, 'name' => 'Cumple', 'slug' => 'cumple-y', 'channel' => 'whatsapp',
        'event' => 'cumpleanos', 'body' => 'OTRO TALLER', 'is_active' => true,
    ]);

    expect(ParaHoy::mensajeCumpleanos($this->cliente))->not->toContain('OTRO TALLER');
});

it('el comercial carga los mensajes del sistema de una vez', function () {
    Livewire::test(ListCommunicationTemplates::class)->callAction('cargar_mensajes');

    expect(CommunicationTemplate::count())->toBe(6)
        ->and(CommunicationTemplate::where('event', 'cumpleanos')->value('body'))->toContain('{nombre}')
        ->and(Mensajes::faltantes())->toBe([]);

    Livewire::test(ListCommunicationTemplates::class)->assertActionHidden('cargar_mensajes');
});

it('al elegir un mensaje se completa el texto de siempre, y se guarda con identificador propio', function () {
    Livewire::test(CreateCommunicationTemplate::class)
        ->fillForm(['event' => 'presupuesto'])
        ->assertFormSet(['channel' => 'whatsapp', 'body' => "Hola {nombre}, le enviamos el presupuesto {codigo} de {vehiculo}.\n\nPuede verlo aquí: {link}"])
        ->fillForm(['body' => 'Hola {nombre}, acá va tu presupuesto: {link}'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CommunicationTemplate::sole())
        ->slug->toStartWith('presupuesto-whatsapp-')
        ->body->toBe('Hola {nombre}, acá va tu presupuesto: {link}');
});
