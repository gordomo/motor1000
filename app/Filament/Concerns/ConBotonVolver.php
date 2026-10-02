<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Botón "Volver" para las páginas de crear, editar y ver.
 *
 * Los usuarios usan mucho la flecha "atrás" del navegador, y después de guardar
 * eso los devuelve a formularios viejos o vacíos. Este botón va siempre a un
 * lugar fijo, decidido al abrir la página:
 *  - a la pantalla de la que vino (listado con sus filtros, ficha, calendario,
 *    tablero...), si es del mismo panel y no es un formulario de crear/editar;
 *  - si no, al listado del recurso.
 *
 * El "Cancelar" de los formularios usa el mismo destino (el de Filament hace
 * history.back(), que tiene el mismo problema).
 */
trait ConBotonVolver
{
    #[Locked]
    public ?string $volverUrl = null;

    /** Livewire lo llama solo al montar la página (hook mount<Trait>). */
    public function mountConBotonVolver(): void
    {
        $this->volverUrl = static::destinoVolver(request());
    }

    protected function volverAction(): Action
    {
        return Action::make('volver')
            ->label(__('Volver'))
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url($this->getVolverUrl());
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->alpineClickHandler(null)
            ->url($this->getVolverUrl());
    }

    public function getVolverUrl(): string
    {
        return $this->volverUrl ?? static::getResource()::getUrl('index');
    }

    /** La pantalla de la que vino el usuario, si sirve como destino; si no, null. */
    protected static function destinoVolver(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! $referer) {
            return null;
        }

        $origen = parse_url($referer);

        // Solo pantallas de este mismo sitio, y no la misma página recargada.
        if (($origen['host'] ?? null) !== $request->getHost()
            || ($origen['path'] ?? '/') === '/' . ltrim($request->path(), '/')) {
            return null;
        }

        try {
            $ruta = app('router')->getRoutes()->match(Request::create($referer, 'GET'));
        } catch (Throwable) {
            return null;
        }

        $nombre = (string) $ruta->getName();
        $panel = 'filament.' . Filament::getCurrentPanel()?->getId() . '.';

        // Volver a un formulario de crear/editar es justo lo que confunde.
        if (! str_starts_with($nombre, $panel)
            || str_ends_with($nombre, '.create')
            || str_ends_with($nombre, '.edit')) {
            return null;
        }

        return $referer;
    }
}
