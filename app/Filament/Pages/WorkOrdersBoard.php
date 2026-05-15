<?php

namespace App\Filament\Pages;

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

    protected static ?string $navigationGroup = 'Taller';

    protected static ?string $navigationLabel = 'Tablero OS';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Tablero de Órdenes';

    protected static string $view = 'filament.pages.work-orders-board';

    public array $columns = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('list')
                ->label('Ver listado')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(WorkOrderResource::getUrl('index')),
            Actions\Action::make('create')
                ->label('Nueva orden')
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
        $tenantId = app('current.tenant')?->id ?? 0;
        $status = WorkOrderStatus::tryFrom($targetStatus);

        if (! $status) {
            return;
        }

        $order = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($orderId);

        if (! $order) {
            return;
        }

        $payload = ['status' => $status];

        if ($status === WorkOrderStatus::Repairing && ! $order->started_at) {
            $payload['started_at'] = now();
        }

        if ($status === WorkOrderStatus::Completed && ! $order->completed_at) {
            $payload['completed_at'] = now();
        }

        if ($status === WorkOrderStatus::Delivered && ! $order->delivered_at) {
            $payload['delivered_at'] = now();
        }

        $order->update($payload);

        Notification::make()
            ->title("{$order->number} movida a {$status->getLabel()}")
            ->success()
            ->send();

        $this->refreshBoard();
    }

    protected function refreshBoard(): void
    {
        $tenantId = app('current.tenant')?->id ?? 0;

        $orders = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
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
                        'customer' => $order->customer?->name ?? 'Cliente no disponible',
                        'vehicle' => trim(($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? '')),
                        'plate' => $order->vehicle?->license_plate ?? 'Sin placa',
                        'mechanic' => $order->mechanic?->name ?? 'Sin asignar',
                        'priority' => $order->priority,
                        'estimated_at' => $order->estimated_at?->format('d/m H:i') ?? 'Sin fecha',
                        'editUrl' => WorkOrderResource::getUrl('edit', ['record' => $order]),
                        'viewUrl' => WorkOrderResource::getUrl('view', ['record' => $order]),
                    ])->values()->all(),
                ];
            })
            ->all();
    }
}
