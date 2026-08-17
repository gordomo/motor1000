<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChecklistItemResource\Pages;
use App\Models\ChecklistItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Pedidos 2 y 17: el taller administra sus propios puntos de revisión, que alimentan
 * el checklist de los presupuestos y de las revisiones.
 *
 * Solo el administrador del taller entra: es configuración, no operación diaria.
 */
class ChecklistItemResource extends Resource
{
    protected static ?string $model = ChecklistItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): ?string
    {
        // Mismo nombre que declara el panel en navigationGroups().
        return __('Configuraciones');
    }

    public static function getModelLabel(): string
    {
        return __('Punto de revisión');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Puntos de revisión');
    }

    // Configuración del taller: mismo criterio que "Mi Taller".
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('categoria')
                        ->label(__('Categoría'))
                        ->placeholder(__('Frenos, Suspensión, Luces...'))
                        ->required()
                        ->maxLength(255)
                        // Sugiere las categorías que ya usa el taller, para no
                        // terminar con "Frenos" y "frenos" como dos grupos.
                        ->datalist(fn (): array => ChecklistItem::query()
                            ->distinct()
                            ->orderBy('categoria')
                            ->pluck('categoria')
                            ->all()),
                    Forms\Components\TextInput::make('nombre_item')
                        ->label(__('Punto a revisar'))
                        ->placeholder(__('Pastillas de freno delanteras'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('Orden'))
                        ->numeric()
                        ->minValue(0)
                        ->helperText(__('Define en qué posición aparece. Vacío = al final.')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Activo'))
                        ->default(true)
                        ->helperText(__('Desactivado no aparece en los nuevos presupuestos ni revisiones, pero no se borra de los ya emitidos.')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('categoria')
                    ->label(__('Categoría'))
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nombre_item')
                    ->label(__('Punto a revisar'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Activo'))
                    ->trueLabel(__('Solo activos'))
                    ->falseLabel(__('Solo inactivos'))
                    ->placeholder(__('Todos')),
                Tables\Filters\SelectFilter::make('categoria')
                    ->label(__('Categoría'))
                    ->options(fn (): array => ChecklistItem::query()
                        ->distinct()
                        ->orderBy('categoria')
                        ->pluck('categoria', 'categoria')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('Todavía no hay puntos de revisión'))
            ->emptyStateDescription(__('Cargá los puntos que querés revisar en cada presupuesto y revisión.'));
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListChecklistItems::route('/'),
            'create' => Pages\CreateChecklistItem::route('/create'),
            'edit'   => Pages\EditChecklistItem::route('/{record}/edit'),
        ];
    }
}
