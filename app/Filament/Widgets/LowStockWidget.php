<?php

namespace App\Filament\Widgets;

use App\Scopes\TenantScope;
use App\Models\InventoryItem;
use App\Support\CurrentTenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Pedido 6: qué repuestos están por debajo del mínimo, a la vista en el tablero.
 * La campanita avisa una vez por día; esto es para verlo en el momento.
 */
class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('Repuestos por debajo del mínimo');
    }

    public static function canView(): bool
    {
        // El mecánico no compra repuestos; esto es para quien repone.
        return auth()->user()?->hasAnyRole(['admin', 'receptionist']) ?? false;
    }

    public function table(Table $table): Table
    {
        $tenantId = CurrentTenant::id() ?? 0;

        return $table
            ->query(
                InventoryItem::withoutGlobalScopes([TenantScope::class])
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->where('min_stock', '>', 0)
                    ->whereColumn('stock_quantity', '<=', 'min_stock')
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Repuesto'))
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('Categoría'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label(__('Stock'))
                    ->numeric(decimalPlaces: 0)
                    ->color(fn (InventoryItem $record): string => $record->stock_quantity <= 0 ? 'danger' : 'warning')
                    ->weight('bold')
                    ->suffix(fn (InventoryItem $record): string => ' ' . $record->unit),
                Tables\Columns\TextColumn::make('min_stock')
                    ->label(__('Mínimo'))
                    ->numeric(decimalPlaces: 0),
                Tables\Columns\TextColumn::make('supplier')
                    ->label(__('Proveedor'))
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('reponer')
                    ->label(__('Ver ficha'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (InventoryItem $record): string => \App\Filament\Resources\InventoryItemResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading(__('Todo el inventario está por encima del mínimo'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
