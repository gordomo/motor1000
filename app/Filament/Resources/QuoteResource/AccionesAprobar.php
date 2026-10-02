<?php

namespace App\Filament\Resources\QuoteResource;

use App\Actions\Quote\AprobarPresupuestoAction;
use App\Enums\QuoteStatus;
use App\Filament\Resources\WorkOrderResource;
use App\Models\Mechanic;
use App\Models\Quote;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use InvalidArgumentException;

/**
 * "Aprobar total" y "Aprobar parcial": se usan en la ficha del presupuesto y
 * en el listado (son dos clases de acción distintas en Filament, con la misma
 * configuración). La lógica está en AprobarPresupuestoAction.
 */
class AccionesAprobar
{
    public static function total(MountableAction $accion): MountableAction
    {
        return self::comunes($accion)
            ->label(__('Aprobar total'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->modalHeading(__('Aprobar el presupuesto completo'))
            ->modalDescription(fn (Quote $record): string => __('Se genera la orden de trabajo con todos los ítems (:total).', [
                'total' => self::plata((float) $record->total),
            ]))
            ->modalSubmitActionLabel(__('Aprobar y generar orden'))
            ->form(self::camposDeLaOrden())
            ->action(fn (Quote $record, array $data, $livewire) => self::aprobar($record, null, $data, $livewire));
    }

    public static function parcial(MountableAction $accion): MountableAction
    {
        return self::comunes($accion)
            ->label(__('Aprobar parcial'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('warning')
            ->modalHeading(__('Aprobar una parte del presupuesto'))
            ->modalDescription(__('Destildá lo que el cliente no aceptó. La orden sale solo con lo tildado, y el presupuesto guarda todo lo que se cotizó.'))
            ->modalSubmitActionLabel(__('Aprobar y generar orden'))
            ->form(fn (Quote $record): array => [
                Forms\Components\CheckboxList::make('aprobados')
                    ->label(__('Ítems que aprobó el cliente'))
                    ->options(collect($record->items ?? [])
                        ->values()
                        ->mapWithKeys(fn (array $i, int $n): array => [$n => sprintf(
                            '%s — %s × %s = %s',
                            $i['descripcion'] ?? __('Ítem'),
                            rtrim(rtrim(number_format((float) ($i['cantidad'] ?? 1), 2, ',', '.'), '0'), ','),
                            self::plata((float) ($i['precio_unitario'] ?? 0)),
                            self::plata((float) ($i['total'] ?? 0)),
                        )])
                        ->all())
                    ->default(array_keys(array_values($record->items ?? [])))
                    ->required()
                    ->validationMessages(['required' => __('Elegí al menos un ítem.')])
                    ->bulkToggleable(),
                Forms\Components\TextInput::make('discount')
                    ->label(__('Descuento para la orden'))
                    ->numeric()
                    ->prefix('$')
                    ->default((float) $record->discount)
                    ->helperText(__('Era el del presupuesto completo: revisalo si el cliente aceptó solo una parte.')),
                ...self::camposDeLaOrden(),
            ])
            ->action(fn (Quote $record, array $data, $livewire) => self::aprobar($record, $data['aprobados'] ?? [], $data, $livewire));
    }

    private static function comunes(MountableAction $accion): MountableAction
    {
        return $accion
            ->visible(fn (Quote $record): bool => $record->status !== QuoteStatus::Rejected
                && ! $record->hasWorkOrder()
                && ! empty($record->items)
                && WorkOrderResource::canCreate());
    }

    /** @return array<Forms\Components\Component> */
    private static function camposDeLaOrden(): array
    {
        return [
            Forms\Components\Select::make('mechanic_id')
                ->label(__('Mecánico asignado'))
                ->options(fn () => Mechanic::where('is_active', true)->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\DateTimePicker::make('estimated_at')
                ->label(__('Fecha promesa de entrega')),
        ];
    }

    private static function aprobar(Quote $record, ?array $aprobados, array $data, $livewire): void
    {
        try {
            $orden = app(AprobarPresupuestoAction::class)->execute($record, $aprobados, $data);
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('Orden :numero creada', ['numero' => $orden->number]))
            ->send();

        $livewire->redirect(WorkOrderResource::getUrl('view', ['record' => $orden]));
    }

    private static function plata(float $monto): string
    {
        return '$' . number_format($monto, 0, ',', '.');
    }
}
