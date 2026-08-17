<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InspectionResource\Pages;
use App\Models\Inspection;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Pedido 17: revisiones. Calcado del presupuesto pero sin precios ni totales.
 * Los puntos a revisar los configura el taller en "Puntos de revisión".
 */
class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

    public static function getModelLabel(): string
    {
        return __('Revisión');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Revisiones');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Datos de la revisión'))
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label(__('Número'))
                        ->disabled()
                        ->placeholder(__('Se asigna al guardar'))
                        ->columnSpan(1),

                    Forms\Components\Select::make('customer_id')
                        ->label(__('Cliente'))
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('vehicle_id', null))
                        ->columnSpan(1),

                    Forms\Components\Select::make('vehicle_id')
                        ->label(__('Vehículo'))
                        ->options(function (Get $get): array {
                            $customerId = $get('customer_id');

                            if (! $customerId) {
                                return [];
                            }

                            return Vehicle::where('customer_id', $customerId)
                                ->get()
                                ->mapWithKeys(fn (Vehicle $v): array => [$v->id => $v->display_name])
                                ->all();
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if ($state && $km = Vehicle::find($state)?->mileage) {
                                $set('mileage', $km);
                            }
                        })
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('mileage')
                        ->label(__('Kilometraje'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix('km')
                        ->columnSpan(1),
                ]),

            Forms\Components\Section::make(__('Check List de revisión'))
                ->description(__('Los puntos se configuran en Configuraciones → Puntos de revisión.'))
                ->collapsible()
                ->schema([
                    Forms\Components\Repeater::make('checklist')
                        ->label('')
                        ->default(fn (): array => \App\Models\ChecklistItem::blankChecklist())
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(12)
                        ->itemLabel(fn (array $state): string =>
                            '[' . ($state['categoria'] ?? '') . '] ' . ($state['nombre_item'] ?? '')
                        )
                        ->schema([
                            Forms\Components\Hidden::make('id_punto'),
                            Forms\Components\Hidden::make('categoria'),

                            Forms\Components\TextInput::make('nombre_item')
                                ->label(__('Punto'))
                                ->disabled()
                                ->dehydrated() // lo usa el PDF
                                ->columnSpan(4),

                            Forms\Components\Radio::make('estado')
                                ->label(__('Estado'))
                                ->options([
                                    'BIEN'    => __('BIEN'),
                                    'REGULAR' => __('REGULAR'),
                                    'MAL'     => __('MAL'),
                                ])
                                ->inline()
                                ->live()
                                // A diferencia del presupuesto con revisión, acá no es
                                // obligatorio: la revisión puede quedar a medias y
                                // completarse después.
                                ->columnSpan(4),

                            Forms\Components\TextInput::make('aclaracion')
                                ->label(__('Aclaración'))
                                ->placeholder(__('Describa la anomalía...'))
                                ->hidden(fn (Get $get): bool => ! in_array($get('estado'), ['REGULAR', 'MAL']))
                                ->columnSpan(4),
                        ]),
                ]),

            Forms\Components\Section::make(__('Notas'))
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('')
                        ->placeholder(__('Observaciones de la revisión, recomendaciones, trabajos sugeridos...'))
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Número'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Fecha'))
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Cliente'))
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('vehicle.license_plate')
                    ->label(__('Patente'))
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('mileage')
                    ->label(__('KM'))
                    ->numeric()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('checked_count')
                    ->label(__('Puntos revisados'))
                    ->badge()
                    ->color('info')
                    ->state(fn (Inspection $record): int => count($record->checkedItems())),
            ])
            ->filters([
                Tables\Filters\Filter::make('con_observaciones')
                    ->label(__('Con puntos en REGULAR o MAL'))
                    ->query(fn ($query) => $query
                        ->where('checklist', 'like', '%REGULAR%')
                        ->orWhere('checklist', 'like', '%MAL%')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Inspection $record): string => route('inspections.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('imprimir')
                    ->label(__('Imprimir'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Inspection $record): string => route('inspections.pdf.stream', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('Todavía no hay revisiones'));
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInspections::route('/'),
            'create' => Pages\CreateInspection::route('/create'),
            'edit'   => Pages\EditInspection::route('/{record}/edit'),
        ];
    }
}
