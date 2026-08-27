<?php

namespace App\Filament\Pages;

use App\Actions\WorkOrder\UpdateWorkOrderStatusAction;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WorkOrdersBoard extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.work-orders-board';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Tablero de Órdenes');
    }

    public static function getNavigationLabel(): string
    {
        return __('Tablero OS');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

    public array $columns = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('list')
                ->label(__('Ver listado'))
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(WorkOrderResource::getUrl('index')),
            Actions\Action::make('create')
                ->label(__('Nueva orden'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(WorkOrderResource::getUrl('create')),
        ];
    }

    public function mount(): void
    {
        $this->refreshBoard();
    }

    public function moveOrder(int $orderId, string $targetStatus): void
    {
        $status = WorkOrderStatus::tryFrom($targetStatus);

        if (! $status) {
            return;
        }

        // Usa el global scope de tenant (igual que el listado y la vista de detalle)
        // para garantizar consistencia: el tablero opera siempre sobre las mismas
        // órdenes del tenant actual.
        $order = WorkOrder::query()->find($orderId);

        if (! $order) {
            return;
        }

        // El tablero actualizaba el estado por su cuenta, salteando la Action: por
        // eso arrastrar a "Completado" no avisaba al cliente. Ahora usa el mismo
        // camino que el botón Avanzar, que además descuenta el stock (pedido 5) y
        // valida quién puede mover qué.
        try {
            app(UpdateWorkOrderStatusAction::class)->execute($order, $status);
        } catch (\DomainException $e) {
            Notification::make()
                ->title(__('No se puede mover la orden'))
                ->body($e->getMessage())
                ->warning()
                ->persistent()
                ->send();

            // Vuelve a dibujar el tablero para que la tarjeta regrese a su columna.
            $this->refreshBoard();

            return;
        }

        Notification::make()
            ->title(__(':number movida a :status', ['number' => $order->number, 'status' => $status->getLabel()]))
            ->success()
            ->send();

        $this->refreshBoard();
    }

    protected function refreshBoard(): void
    {
        // Lee exactamente las mismas órdenes que el listado (global scope de tenant),
        // evitando el desfasaje que mostraba datos de demo / órdenes huérfanas.
        $orders = WorkOrder::query()
            ->with(['customer:id,name', 'vehicle:id,license_plate,brand,model', 'mechanic:id,name'])
            ->latest()
            ->get()
            ->groupBy(fn (WorkOrder $order) => $order->status->value);

        $this->columns = collect(WorkOrderStatus::cases())
            ->map(function (WorkOrderStatus $status) use ($orders): array {
                return [
                    'value' => $status->value,
                    'label' => $status->getLabel(),
                    'items' => collect($orders->get($status->value, []))->map(fn (WorkOrder $order) => [
                        'id' => $order->id,
                        'number' => $order->number,
                        'customer' => $order->customer?->name ?? __('Cliente no disponible'),
                        'vehicle' => trim(($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? '')),
                        'plate' => $order->vehicle?->license_plate ?? __('Sin placa'),
                        'mechanic' => $order->mechanic?->name ?? __('Sin asignar'),
                        'priority' => $order->priority,
                        'estimated_at' => $order->estimated_at?->format('d/m H:i') ?? __('Sin fecha'),
                        // El cliente pidió que se note cuando algo pasó con la orden.
                        'blocked' => $order->isBlocked(),
                        'blockedReason' => $order->blocked_reason,
                        'hasIssues' => $order->hasIssues(),
                        'issuesCount' => count($order->issuePoints()),
                        'editUrl' => WorkOrderResource::getUrl('edit', ['record' => $order]),
                        'viewUrl' => WorkOrderResource::getUrl('view', ['record' => $order]),
                    ])->values()->all(),
                ];
            })
            ->all();
    }
}
