<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('Ítem de inventario');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Inventario');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

    public static function getNavigationBadge(): ?string
    {
        $low = InventoryItem::whereColumn('stock_quantity', '<=', 'min_stock')->count();
        return $low > 0 ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): string { return 'danger'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('code')->label(__('Código')),
                Forms\Components\TextInput::make('name')->label(__('Nombre'))->required()->columnSpan(2),
                Forms\Components\TextInput::make('brand')->label(__('Marca')),
                Forms\Components\TextInput::make('category')->label(__('Categoría')),
                Forms\Components\Select::make('unit')
                    ->label(__('Unidad'))
                    ->options(['un' => __('Unidad'), 'kg' => __('Kg'), 'lt' => __('Litro'), 'm' => __('Metro'), 'cx' => __('Caja')])
                    ->default('un'),
                Forms\Components\TextInput::make('cost_price')->label(__('Precio de costo'))->numeric()->prefix('$'),
                Forms\Components\TextInput::make('sale_price')->label(__('Precio de venta'))->numeric()->prefix('$'),
                Forms\Components\TextInput::make('stock_quantity')->label(__('Inventario actual'))->numeric()->default(0),
                Forms\Components\TextInput::make('min_stock')->label(__('Inventario mínimo'))->numeric()->default(0),
                Forms\Components\TextInput::make('location')->label(__('Ubicación (Estantería)')),
                Forms\Components\TextInput::make('supplier')->label(__('Proveedor')),
                Forms\Components\Toggle::make('is_active')->label(__('Activo'))->default(true),
                Forms\Components\Textarea::make('notes')->label(__('Observaciones'))->columnSpan(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label(__('Código'))->searchable(),
                Tables\Columns\TextColumn::make('name')->label(__('Nombre'))->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('category')->label(__('Categoría')),
                Tables\Columns\TextColumn::make('stock_quantity')->label(__('Inventario'))
                    ->numeric(2)
                    ->color(fn(InventoryItem $r) => $r->isLowStock() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('min_stock')->label(__('Mín.'))->numeric(2),
                Tables\Columns\TextColumn::make('sale_price')->label(__('Precio de venta'))->money('ARS'),
                Tables\Columns\IconColumn::make('is_active')->label(__('Activo'))->boolean(),
            ])
            ->filters([
                Tables\Filters\Filter::make('low_stock')
                    ->label(__('Inventario bajo'))
                    ->query(fn(Builder $q) => $q->whereColumn('stock_quantity', '<=', 'min_stock')),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Activo')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'edit'   => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
