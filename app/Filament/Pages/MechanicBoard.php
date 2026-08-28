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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manual')
                ->label(__('¿Cómo se usa?'))
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->url(fn (): string => Manual::getUrl()),
        ];
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

        // Los títulos salen del enum: la misma orden tiene que llamarse igual acá,
        // en el listado, en el kanban y en el PDF. Antes usaba nombres inventados
        // ("En el taller ahora", "Para empezar") que no existían en ningún otro lado.
        return collect([WorkOrderStatus::Repairing, WorkOrderStatus::Received])
            ->map(fn (WorkOrderStatus $status): array => [
                'estado'    => $status,
                'titulo'    => $status->getLabel(),
                'subtitulo' => match ($status) {
                    WorkOrderStatus::Repairing => __('Autos que se están trabajando'),
                    WorkOrderStatus::Received  => __('Autos que esperan que alguien los tome'),
                    default                    => '',
                },
                'items'     => $orders->where('status', $status)->values(),
            ])
            ->all();
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
            ->modalHeading(__('Cerrar la orden'))
            ->modalDescription(__('Contá qué se hizo en el auto. Esto queda en el comprobante del cliente.'))
            ->modalSubmitActionLabel(__('Cerrar la orden'))
            ->fillForm(fn (array $arguments): array => [
                'work_performed' => $this->orden($arguments)?->work_performed,
            ])
            // Los puntos ya se marcan en la tarjeta: acá solo falta el relato.
            ->form([
                Forms\Components\Textarea::make('work_performed')
                    ->label(__('Trabajo realizado'))
                    ->placeholder(__('Se cambiaron las pastillas delanteras y se purgó el circuito...'))
                    ->required()
                    ->rows(4),
            ])
            ->action(function (array $arguments, array $data): void {
                $order = $this->orden($arguments);

                if (! $order) {
                    return;
                }

                // Se guarda antes de validar el paso: si algo falta, no pierde el texto.
                $order->forceFill(['work_performed' => $data['work_performed']])->saveQuietly();

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

    // ─── Marcar los puntos desde la tarjeta ───────────────────────────────────

    /**
     * Marca un punto como hecho sin abrir ningún modal: en una tablet, un toque.
     * Antes los puntos se listaban con iconos que no hacían nada y había que
     * abrir el modal de cierre para poder tocarlos.
     */
    public function marcarHecho(int $orderId, int $indice): void
    {
        $this->actualizarPunto($orderId, $indice, WorkOrder::PUNTO_HECHO, '');
    }

    /** Vuelve el punto a sin marcar, por si se tocó de más. */
    public function desmarcarPunto(int $orderId, int $indice): void
    {
        $this->actualizarPunto($orderId, $indice, null, '');
    }

    public function noSePudoAction(): Action
    {
        return Action::make('noSePudo')
            ->label(__('No se pudo'))
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            ->modalHeading(__('¿Por qué no se pudo?'))
            ->modalSubmitActionLabel(__('Guardar'))
            ->form([
                Forms\Components\Textarea::make('aclaracion')
                    ->label(__('Contá qué pasó'))
                    ->placeholder(__('Falta el repuesto, apareció otra falla, no entraba la herramienta...'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->actualizarPunto(
                    (int) $arguments['order'],
                    (int) $arguments['indice'],
                    WorkOrder::PUNTO_NO_SE_PUDO,
                    $data['aclaracion'],
                );
            });
    }

    private function actualizarPunto(int $orderId, int $indice, ?string $estado, string $aclaracion): void
    {
        $order = WorkOrder::query()->find($orderId);

        if (! $order) {
            return;
        }

        // Se parte de la lista normalizada y se guarda normalizada: así las órdenes
        // que traían el formato viejo quedan convertidas la primera vez que alguien
        // toca un punto, sin necesidad de migrar datos a mano.
        $checklist = $order->workChecklist();

        if (! array_key_exists($indice, $checklist)) {
            return;
        }

        $checklist[$indice]['estado']     = $estado;
        $checklist[$indice]['aclaracion'] = $aclaracion;

        $order->forceFill(['checklist' => $checklist])->saveQuietly();
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
