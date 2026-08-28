<?php

namespace App\Filament\Pages;

use App\Actions\WorkOrder\UpdateWorkOrderStatusAction;
use App\Enums\WorkOrderStatus;
use App\Models\Mechanic;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Tablero del taller: la vista del mecánico.
 *
 * Pensada para una tablet o un totem con pantalla táctil: pocas cosas, botones
 * grandes y ningún dato de plata. El mecánico ve todas las órdenes, elige en cuál
 * se pone a trabajar y marca qué hizo. Nada más.
 *
 * La sesión del totem es compartida, así que al tomar una orden se pregunta qué
 * mecánico es: queda asignado a la orden y registrado en el historial.
 */
class MechanicBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.mechanic-board';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Tablero del taller');
    }

    public static function getNavigationLabel(): string
    {
        return __('Tablero del taller');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['mechanic', 'admin']) ?? false;
    }

    /** Órdenes en juego, agrupadas por lo que el mecánico tiene que hacer con ellas. */
    public function getGruposProperty(): array
    {
        $orders = WorkOrder::query()
            ->whereIn('status', [WorkOrderStatus::Received->value, WorkOrderStatus::Repairing->value])
            ->with(['customer:id,name', 'vehicle:id,license_plate,brand,model', 'mechanic:id,name'])
            // CASE en vez de FIELD(): FIELD es de MySQL y los tests corren en SQLite.
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderBy('created_at')
            ->get();

        return [
            [
                'titulo'   => __('En el taller ahora'),
                'subtitulo' => __('Órdenes que se están trabajando'),
                'items'    => $orders->where('status', WorkOrderStatus::Repairing)->values(),
            ],
            [
                'titulo'   => __('Para empezar'),
                'subtitulo' => __('Autos recibidos, esperando que alguien los tome'),
                'items'    => $orders->where('status', WorkOrderStatus::Received)->values(),
            ],
        ];
    }

    /** Lista de mecánicos para el "¿quién sos?". */
    private function mecanicos(): array
    {
        return Mechanic::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function orden(array $arguments): ?WorkOrder
    {
        return WorkOrder::query()->find($arguments['order'] ?? null);
    }

    // ─── Acciones ─────────────────────────────────────────────────────────────

    public function empezarAction(): Action
    {
        return Action::make('empezar')
            ->label(__('Me pongo a trabajar'))
            ->icon('heroicon-o-play')
            ->color('success')
            ->modalHeading(__('¿Quién se pone a trabajar?'))
            ->modalSubmitActionLabel(__('Empezar'))
            ->form(fn (): array => [
                Forms\Components\Radio::make('mechanic_id')
                    ->label('')
                    ->options($this->mecanicos())
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $order = $this->orden($arguments);

                if (! $order) {
                    return;
                }

                try {
                    app(UpdateWorkOrderStatusAction::class)->execute(
                        $order,
                        WorkOrderStatus::Repairing,
                        options: ['mechanic_id' => (int) $data['mechanic_id']],
                    );
                } catch (\DomainException $e) {
                    $this->avisar($e->getMessage());

                    return;
                }

                Notification::make()
                    ->title(__('A trabajar: :number', ['number' => $order->number]))
                    ->success()
                    ->send();
            });
    }

    /**
     * Órdenes que ya estaban en reparación antes de este flujo quedaron sin
     * mecánico asignado. Esto permite que alguien se haga cargo sin cambiar el
     * estado, para que el trabajo no quede sin dueño.
     */
    public function hacerseCargoAction(): Action
    {
        return Action::make('hacerseCargo')
            ->label(__('Me hago cargo'))
            ->icon('heroicon-o-hand-thumb-up')
            ->color('primary')
            ->modalHeading(__('¿Quién se hace cargo?'))
            ->modalSubmitActionLabel(__('Confirmar'))
            ->form(fn (): array => [
                Forms\Components\Radio::make('mechanic_id')
                    ->label('')
                    ->options($this->mecanicos())
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $order = $this->orden($arguments);
                $order?->forceFill(['mechanic_id' => (int) $data['mechanic_id']])->saveQuietly();

                Notification::make()->title(__('Listo, quedó asignada.'))->success()->send();
            });
    }

    public function trabarAction(): Action
    {
        return Action::make('trabar')
            ->label(__('No puedo empezar'))
            ->icon('heroicon-o-hand-raised')
            ->color('danger')
            ->modalHeading(__('¿Qué falta?'))
            ->modalDescription(__('La orden queda marcada para que la vean en el mostrador. No cambia de columna.'))
            ->modalSubmitActionLabel(__('Avisar'))
            ->form([
                Forms\Components\Textarea::make('motivo')
                    ->label(__('Qué falta para poder trabajar'))
                    ->placeholder(__('Falta el filtro de aceite, no está la llave de la traba...'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $arguments, array $data): void {
                $order = $this->orden($arguments);
                $order?->block($data['motivo']);

                Notification::make()
                    ->title(__('Avisado. La orden quedó marcada.'))
                    ->success()
                    ->send();
            });
    }

    public function destrabarAction(): Action
    {
        return Action::make('destrabar')
            ->label(__('Ya se resolvió'))
            ->icon('heroicon-o-check')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $this->orden($arguments)?->unblock();
            });
    }

    public function completarAction(): Action
    {
        return Action::make('completar')
            ->label(__('Terminé el trabajo'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->modalHeading(__('¿Qué se hizo?'))
            ->modalSubmitActionLabel(__('Marcar como terminado'))
            ->modalWidth('3xl')
            ->fillForm(fn (array $arguments): array => [
                'checklist'      => $this->orden($arguments)?->checklist ?? [],
                'work_performed' => $this->orden($arguments)?->work_performed,
            ])
            ->form([
                Forms\Components\Repeater::make('checklist')
                    ->label(__('Puntos a trabajar'))
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): string => trim(
                        ($state['nombre_item'] ?? '') .
                        (($state['estado_presupuesto'] ?? null) ? ' · ' . __('venía') . ' ' . $state['estado_presupuesto'] : '')
                    ))
                    ->schema([
                        Forms\Components\Hidden::make('id_punto'),
                        Forms\Components\Hidden::make('categoria'),
                        Forms\Components\Hidden::make('nombre_item'),
                        Forms\Components\Hidden::make('estado_presupuesto'),
                        Forms\Components\Hidden::make('observacion_previa'),
                        Forms\Components\Radio::make('estado')
                            ->label('')
                            ->options(WorkOrder::PUNTO_ESTADOS)
                            ->inline()
                            ->live()
                            ->required(),
                        Forms\Components\Textarea::make('aclaracion')
                            ->label(__('Por qué no se pudo'))
                            ->rows(2)
                            ->required(fn (Forms\Get $get): bool => $get('estado') === WorkOrder::PUNTO_NO_SE_PUDO)
                            ->visible(fn (Forms\Get $get): bool => $get('estado') === WorkOrder::PUNTO_NO_SE_PUDO),
                    ])
                    ->visible(fn (Forms\Get $get): bool => filled($get('checklist'))),
                Forms\Components\Textarea::make('work_performed')
                    ->label(__('Trabajo realizado'))
                    ->placeholder(__('Contá qué se hizo en el auto...'))
                    ->required()
                    ->rows(4),
            ])
            ->action(function (array $arguments, array $data): void {
                $order = $this->orden($arguments);

                if (! $order) {
                    return;
                }

                // Se guarda lo que cargó el mecánico antes de validar el paso: si algo
                // falta, no pierde lo escrito.
                $order->forceFill([
                    'checklist'      => $data['checklist'] ?? $order->checklist,
                    'work_performed' => $data['work_performed'],
                ])->saveQuietly();

                try {
                    app(UpdateWorkOrderStatusAction::class)->execute($order->refresh(), WorkOrderStatus::Completed);
                } catch (\DomainException $e) {
                    $this->avisar($e->getMessage());

                    return;
                }

                Notification::make()
                    ->title(__('Listo: :number terminada', ['number' => $order->number]))
                    ->success()
                    ->send();
            });
    }

    private function avisar(string $mensaje): void
    {
        Notification::make()
            ->title(__('Falta algo'))
            ->body($mensaje)
            ->warning()
            ->persistent()
            ->send();
    }
}
