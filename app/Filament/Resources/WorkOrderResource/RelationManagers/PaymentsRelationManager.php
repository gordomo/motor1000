<?php

namespace App\Filament\Resources\WorkOrderResource\RelationManagers;

use App\Models\Payment;
use App\Models\WorkOrder;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Los cobros de la orden.
 *
 * Existe para que el cobro no sea un dato que se carga y no se puede revisar ni
 * corregir: acá se ve qué entró, cuándo, de qué forma y quién lo registró, y se
 * puede borrar uno cargado por error. Borrarlo recalcula el estado de pago de la
 * orden y la saca de los números cobrados.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Cobros';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        // El mecánico no ve plata.
        return auth()->user()?->hasAnyRole(['admin', 'receptionist']) ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var WorkOrder $orden */
        $orden = $this->getOwnerRecord();

        return $table
            ->defaultSort('paid_at')
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('Fecha'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Monto'))
                    ->money('ARS')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('method')
                    ->label(__('Forma de pago'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (Payment $record): string => $record->methodLabel()),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Tipo'))
                    ->formatStateUsing(fn (Payment $record): string => $record->typeLabel()),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Registrado por'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')
                    ->label(__('Observaciones'))
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->actions([
                // Corregir y borrar quedan solo para el administrador (PaymentPolicy):
                // es plata que ya está contada en los números del taller. Filament
                // esconde los botones solo con verlos, sin necesidad de repetir la
                // condición acá.
                Tables\Actions\EditAction::make()
                    ->label(__('Corregir'))
                    ->modalHeading(__('Corregir el cobro'))
                    ->modalDescription(__('Se recalcula el estado de pago de la orden y los números del tablero.'))
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label(__('Monto cobrado'))
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\Select::make('method')
                            ->label(__('Forma de pago'))
                            ->options(Payment::METHODS)
                            ->required(),
                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label(__('Cuándo se cobró'))
                            ->maxDate(now())
                            ->required()
                            ->helperText(__('En los números, el cobro cuenta en esta fecha.')),
                        Forms\Components\Textarea::make('notes')
                            ->label(__('Observaciones'))
                            ->rows(2),
                    ]),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Borrar cobro'))
                    ->modalHeading(__('Borrar este cobro'))
                    ->modalDescription(__('La orden vuelve a contar como pendiente de cobro por ese monto.')),
            ])
            ->emptyStateHeading(__('Todavía no se registró ningún cobro'))
            ->emptyStateDescription(fn (): string => $orden->isFree()
                ? __('Esta orden es sin cargo, no hay nada que cobrar.')
                : __('Usá "Registrar cobro" en el listado de órdenes para cargar la forma de pago.'))
            ->paginated(false);
    }
}
