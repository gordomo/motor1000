<?php

namespace App\Filament\Resources;

use App\Enums\QuoteStatus;
use App\Enums\QuoteType;
use App\Filament\Resources\QuoteResource\Pages;
use App\Models\Quote;
use App\Models\Vehicle;
use App\Filament\Concerns\HiddenFromMechanics;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuoteResource extends Resource
{
    use HiddenFromMechanics;

    /**
     * Con la orden ya generada el presupuesto no se edita: los cambios no
     * pasaban a la orden y quedaban dos versiones distintas.
     */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny() && ! $record->hasWorkOrder();
    }

    protected static ?string $model = Quote::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

    public static function getModelLabel(): string
    {
        return __('Presupuesto');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Presupuestos');
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Presupuestos esperando que el cliente los apruebe o rechace');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Quote::where('status', QuoteStatus::Pending->value)->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    // ─── Form ─────────────────────────────────────────────────────────────────
    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Encabezado ──────────────────────────────────────────────────
            Forms\Components\Section::make(__('Identificación'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label(__('Nº Presupuesto'))
                        ->disabled()
                        ->placeholder(__('Se genera automáticamente'))
                        ->columnSpan(1),

                    Forms\Components\Select::make('customer_id')
                        ->label(__('Cliente'))
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn (Set $set) => $set('vehicle_id', null))
                        ->columnSpan(1),

                    Forms\Components\Select::make('vehicle_id')
                        ->label(__('Vehículo'))
                        ->options(function (Get $get): array {
                            $customerId = $get('customer_id');
                            if (! $customerId) return [];
                            return Vehicle::where('customer_id', $customerId)
                                ->get()
                                ->mapWithKeys(fn ($v) => [$v->id => $v->display_name])
                                ->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->reactive()
                        // Precarga el KM del vehículo elegido (pedido 11).
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
                        // Pedido 11: obligatorio al presupuestar.
                        ->required()
                        ->suffix('km')
                        ->columnSpan(1),

                    Forms\Components\Select::make('status')
                        ->label(__('Estado'))
                        ->options(QuoteStatus::class)
                        ->default(QuoteStatus::Pending)
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\DateTimePicker::make('created_at')
                        ->label(__('Fecha'))
                        ->disabled()
                        ->columnSpan(1),

                    // Pedido 2: define si hay que completar el checklist o no.
                    Forms\Components\Radio::make('type')
                        ->label(__('Tipo de presupuesto'))
                        ->options(QuoteType::class)
                        ->descriptions(collect(QuoteType::cases())
                            ->mapWithKeys(fn (QuoteType $t): array => [$t->value => $t->getDescription()])
                            ->all())
                        ->default(QuoteType::ConChecklist)
                        ->required()
                        ->live()
                        ->inline()
                        ->columnSpan(2),
                ]),

            // ── Falla detectada ─────────────────────────────────────────────
            Forms\Components\Section::make(__('Diagnóstico'))
                ->schema([
                    Forms\Components\Textarea::make('detected_fault')
                        ->label(__('Falla detectada'))
                        ->placeholder(__('Describa el problema reportado por el cliente o detectado en la inspección...'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            // ── Checklist de inspección visual ──────────────────────────────
            // La cantidad de puntos ya no es fija: la configura cada taller
            // (pedido 2). La sección se esconde en los presupuestos sin revisión.
            Forms\Components\Section::make(__('Check List de inspección visual'))
                ->collapsible()
                ->visible(fn (Get $get): bool => ($get('type') ?? QuoteType::ConChecklist->value) === QuoteType::ConChecklist->value)
                ->schema([
                    Forms\Components\Repeater::make('checklist')
                        ->label('')
                        ->default(fn (): array => Quote::defaultChecklist())
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(12)
                        ->itemLabel(fn (array $state): string =>
                            "[{$state['categoria']}] {$state['nombre_item']}"
                        )
                        ->schema([
                            Forms\Components\Hidden::make('id_punto'),
                            Forms\Components\Hidden::make('categoria'),

                            Forms\Components\TextInput::make('nombre_item')
                                ->label(__('Punto'))
                                ->disabled()
                                ->dehydrated() // que persista aunque esté disabled (lo usa el PDF)
                                ->columnSpan(4),

                            Forms\Components\Radio::make('estado')
                                ->label(__('Estado'))
                                ->options([
                                    'BIEN'    => __('BIEN'),
                                    'REGULAR' => __('REGULAR'),
                                    'MAL'     => __('MAL'),
                                ])
                                ->inline()
                                ->reactive()
                                // Obligatorio solo en los presupuestos con revisión.
                                // Este único required() era lo que impedía guardar un
                                // presupuesto sin completar los 20 puntos.
                                ->required(fn (Get $get): bool =>
                                    ($get('../../type') ?? QuoteType::ConChecklist->value) === QuoteType::ConChecklist->value
                                )
                                ->columnSpan(4),

                            Forms\Components\TextInput::make('aclaracion')
                                ->label(__('Aclaración'))
                                ->placeholder(__('Describa la anomalía...'))
                                ->hidden(fn (Get $get): bool => ! in_array($get('estado'), ['REGULAR', 'MAL']))
                                ->columnSpan(4),
                        ]),
                ]),

            // ── Items del presupuesto ────────────────────────────────────────
            Forms\Components\Section::make(__('Items del presupuesto'))
                ->schema([
                    Forms\Components\Placeholder::make('aprobacion')
                        ->label(__('Aprobación'))
                        ->visible(fn (?Quote $record): bool => (bool) $record?->esAprobacionParcial())
                        ->content(fn (Quote $record): string => __('Aprobado parcialmente: :aprobados de :total ítems (:monto). No aceptado: :no.', [
                            'aprobados' => count($record->items ?? []) - count($record->itemsNoAprobados()),
                            'total'     => count($record->items ?? []),
                            'monto'     => '$' . number_format($record->subtotalAprobado(), 0, ',', '.'),
                            'no'        => collect($record->itemsNoAprobados())->pluck('descripcion')->implode(', '),
                        ])),

                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->columns(12)
                        ->defaultItems(1)
                        ->schema([
                            Forms\Components\Select::make('tipo')
                                ->label(__('Tipo'))
                                ->options([
                                    'repuesto'    => __('Repuesto'),
                                    'mano_de_obra' => __('Mano de obra'),
                                    'otro'        => __('Otro'),
                                ])
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('descripcion')
                                ->label(__('Descripción'))
                                ->required()
                                ->columnSpan(4),

                            Forms\Components\TextInput::make('cantidad')
                                ->label(__('Cant.'))
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $set('total', (float)$get('cantidad') * (float)$get('precio_unitario'));
                                })
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('precio_unitario')
                                ->label(__('P. Unitario'))
                                ->numeric()
                                ->prefix('$')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $set('total', (float)$get('cantidad') * (float)$get('precio_unitario'));
                                })
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('total')
                                ->label(__('Total'))
                                ->numeric()
                                ->prefix('$')
                                ->disabled()
                                ->columnSpan(2),
                        ])
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            $items = $get('items') ?? [];
                            $subtotal = collect($items)->sum(fn($i) => (float)($i['total'] ?? 0));
                            $set('subtotal', $subtotal);
                            $discount = (float)($get('discount') ?? 0);
                            $tax      = (float)($get('tax') ?? 0);
                            $set('total', max(0, $subtotal + $tax - $discount));
                        }),

                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label(__('Subtotal'))
                            ->numeric()->prefix('$')->disabled(),

                        Forms\Components\TextInput::make('tax')
                            ->label(__('Impuestos'))
                            ->numeric()->prefix('$')->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('total', max(0, (float)$get('subtotal') + (float)$get('tax') - (float)($get('discount') ?? 0)));
                            }),

                        Forms\Components\TextInput::make('discount')
                            ->label(__('Descuento'))
                            ->numeric()->prefix('$')->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('total', max(0, (float)$get('subtotal') + (float)($get('tax') ?? 0) - (float)$get('discount')));
                            }),

                        Forms\Components\TextInput::make('total')
                            ->label(__('TOTAL'))
                            ->numeric()->prefix('$')->disabled()
                            ->extraInputAttributes(['class' => 'font-bold text-lg']),
                    ]),
                ]),

            // ── Notas ────────────────────────────────────────────────────────
            Forms\Components\Section::make(__('Notas internas'))
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    // ─── Table ────────────────────────────────────────────────────────────────
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Nº'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Cliente'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vehicle.license_plate')
                    ->label(__('Patente'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('vehicle.brand')
                    ->label(__('Vehículo'))
                    ->formatStateUsing(fn ($state, $record) =>
                        "{$record->vehicle?->brand} {$record->vehicle?->model}"
                    ),

                Tables\Columns\TextColumn::make('total')
                    ->label(__('Total'))
                    ->money('ARS')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->formatStateUsing(fn ($state, Quote $record): string => $record->esAprobacionParcial()
                        ? __('Aprobado parcial')
                        : ($state instanceof QuoteStatus ? $state->getLabel() : (string) $state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Fecha'))
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Estado'))
                    ->options(QuoteStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // Aprobar total / parcial: generan la orden (ver AccionesAprobar).
                QuoteResource\AccionesAprobar::total(Tables\Actions\Action::make('aprobar_total')),
                QuoteResource\AccionesAprobar::parcial(Tables\Actions\Action::make('aprobar_parcial')),

                // Botón PDF
                Tables\Actions\Action::make('pdf')
                    ->label(__('PDF'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Quote $record): string => route('quotes.pdf', $record))
                    ->openUrlInNewTab(),

                // Botón WhatsApp
                Tables\Actions\Action::make('whatsapp')
                    ->label(__('WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->url(function (Quote $record): string {
                        $pdfUrl  = \App\Http\Controllers\QuotePdfController::linkPublico($record);
                        // ?-> porque Customer usa SoftDeletes y la relación puede venir null.
                        $name = $record->customer?->name ?? '';
                        // Texto editable en Plantillas de comunicación (evento "presupuesto").
                        $msg = urlencode(\App\Support\Mensajes::cuerpo('presupuesto', 'whatsapp', [
                            'nombre'   => $name,
                            'taller'   => \App\Support\CurrentTenant::get()?->name,
                            'codigo'   => $record->code,
                            'vehiculo' => $record->vehicle?->display_name,
                            'link'     => $pdfUrl,
                        ]));
                        $phone = preg_replace('/\D/', '', $record->customer?->whatsapp ?? $record->customer?->phone ?? '');
                        return "https://wa.me/{$phone}?text={$msg}";
                    })
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'view'   => Pages\ViewQuote::route('/{record}'),
            'edit'   => Pages\EditQuote::route('/{record}/edit'),
        ];
    }
}
