<?php

namespace App\Filament\Resources;

use App\Enums\QuoteStatus;
use App\Filament\Resources\QuoteResource\Pages;
use App\Models\Mechanic;
use App\Models\Quote;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuoteResource extends Resource
{
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

    public static function getNavigationBadge(): ?string
    {
        return (string) Quote::whereIn('status', ['draft', 'sent'])->count();
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
                        ->columnSpan(1),

                    Forms\Components\Select::make('status')
                        ->label(__('Estado'))
                        ->options(QuoteStatus::class)
                        ->default(QuoteStatus::Draft)
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\DateTimePicker::make('created_at')
                        ->label(__('Fecha'))
                        ->disabled()
                        ->columnSpan(1),
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

            // ── Checklist 20 puntos ─────────────────────────────────────────
            Forms\Components\Section::make(__('Check List de inspección visual (20 puntos)'))
                ->collapsible()
                ->schema([
                    Forms\Components\Repeater::make('checklist')
                        ->label('')
                        ->default(Quote::defaultChecklist())
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
                                ->required()
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
                                ->reactive()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $set('total', (float)$get('cantidad') * (float)$get('precio_unitario'));
                                })
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('precio_unitario')
                                ->label(__('P. Unitario'))
                                ->numeric()
                                ->prefix('$')
                                ->reactive()
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
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('total', max(0, (float)$get('subtotal') + (float)$get('tax') - (float)($get('discount') ?? 0)));
                            }),

                        Forms\Components\TextInput::make('discount')
                            ->label(__('Descuento'))
                            ->numeric()->prefix('$')->default(0)
                            ->reactive()
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
                    ->badge(),

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

                // Botón "Generar OT" solo si está Aceptado y no tiene OT aún
                Tables\Actions\Action::make('generate_work_order')
                    ->label(__('Generar OT'))
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('success')
                    ->visible(fn (Quote $record): bool =>
                        $record->isAccepted() && ! $record->hasWorkOrder()
                    )
                    ->requiresConfirmation()
                    ->modalHeading(__('Generar Orden de Trabajo'))
                    ->modalDescription(__('¿Confirma que desea generar la Orden de Trabajo a partir de este presupuesto aceptado?'))
                    ->form([
                        Forms\Components\Select::make('mechanic_id')
                            ->label(__('Mecánico asignado'))
                            ->options(fn () => Mechanic::where('is_active', true)->pluck('name', 'id'))
                            ->searchable(),
                        Forms\Components\DateTimePicker::make('estimated_at')
                            ->label(__('Fecha promesa de entrega')),
                    ])
                    ->action(function (Quote $record, array $data): void {
                        $items = collect($record->items ?? [])->map(fn ($i) => [
                            'type'        => $i['tipo'] === 'mano_de_obra' ? 'labor' : ($i['tipo'] === 'repuesto' ? 'part' : 'other'),
                            'description' => $i['descripcion'],
                            'quantity'    => $i['cantidad'] ?? 1,
                            'unit_price'  => $i['precio_unitario'] ?? 0,
                            'total'       => $i['total'] ?? 0,
                        ])->toArray();

                        $wo = WorkOrder::create([
                            'tenant_id'   => $record->tenant_id,
                            'customer_id' => $record->customer_id,
                            'vehicle_id'  => $record->vehicle_id,
                            'quote_id'    => $record->id,
                            'mechanic_id' => $data['mechanic_id'] ?? null,
                            'estimated_at' => $data['estimated_at'] ?? null,
                            'complaint'   => $record->detected_fault,
                            'status'      => 'received',
                            'mileage_in'  => $record->vehicle?->mileage ?? 0,
                            'discount'    => $record->discount,
                        ]);

                        foreach ($items as $item) {
                            $wo->items()->create($item);
                        }
                        $wo->recalculateTotal();

                        Notification::make()
                            ->success()
                            ->title('Orden de Trabajo creada')
                            ->body("OT {$wo->number} generada correctamente.")
                            ->send();
                    }),

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
                        $pdfUrl  = route('quotes.pdf.stream', $record);
                        $msg = urlencode(
                            "Hola {$record->customer->name}, le enviamos el presupuesto {$record->code} de {$record->vehicle?->display_name}.\n\nPuede verlo aquí: {$pdfUrl}"
                        );
                        $phone = preg_replace('/\D/', '', $record->customer->whatsapp ?? $record->customer->phone ?? '');
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
