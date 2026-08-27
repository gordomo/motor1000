<?php

namespace App\Filament\Resources;

use App\Actions\WorkOrder\UpdateWorkOrderStatusAction;
use App\Enums\WorkOrderStatus;
use App\Filament\Pages\WorkOrdersBoard;
use App\Filament\Resources\WorkOrderResource\Pages;
use App\Services\Inventory\WorkOrderStockService;
use App\Services\Pdf\BulkPdfZipService;
use App\Support\WorkOrderTransitions;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Mechanic;
use App\Models\Payment;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

    public static function getModelLabel(): string
    {
        return __('Orden de Servicio');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Órdenes de Servicio');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) WorkOrder::whereNotIn('status', ['delivered'])->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return WorkOrder::whereNotIn('status', ['delivered'])->count() > 10
            ? 'warning' : 'primary';
    }

    public static function getNavigationUrl(): string
    {
        return WorkOrdersBoard::getUrl();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Identificación'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label(__('Cliente'))
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn($set) => $set('vehicle_id', null)),
                    Forms\Components\Select::make('vehicle_id')
                        ->label(__('Vehículo'))
                        ->options(function (Forms\Get $get) {
                            $customerId = $get('customer_id');
                            if (! $customerId) return [];
                            return Vehicle::where('customer_id', $customerId)
                                ->get()
                                ->mapWithKeys(fn($v) => [$v->id => $v->display_name]);
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        // Precarga el KM del vehículo elegido (pedido 11).
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            if ($state && $km = Vehicle::find($state)?->mileage) {
                                $set('mileage_in', $km);
                            }
                        }),
                    Forms\Components\Select::make('mechanic_id')
                        ->label(__('Mecánico'))
                        // Se usa options() en vez de relationship(...closure) para evitar el
                        // error "qualifyColumn() on null" de Filament al modificar la query de
                        // la relación (mismo problema que rompía el alta de presupuestos).
                        ->options(fn () => Mechanic::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Forms\Components\Select::make('priority')
                        ->label(__('Prioridad'))
                        ->options([
                            'low'    => __('Baja'),
                            'normal' => __('Normal'),
                            'high'   => __('Alta'),
                            'urgent' => __('Urgente'),
                        ])
                        ->default('normal')
                        ->required(),
                    Forms\Components\TextInput::make('mileage_in')
                        ->label(__('KM Entrada'))
                        ->numeric()
                        ->minValue(0)
                        // Pedido 11: obligatorio al abrir la orden.
                        ->required()
                        ->helperText(__('Se toma del vehículo y actualiza su kilometraje si es mayor.')),
                    Forms\Components\DateTimePicker::make('estimated_at')
                        ->label(__('Previsión de Entrega')),
                ]),
            Forms\Components\Section::make(__('Descripción'))
                ->schema([
                    Forms\Components\Textarea::make('complaint')
                        ->label(__('Queja del cliente'))
                        ->required()
                        ->rows(3),
                    Forms\Components\Textarea::make('diagnosis')
                        ->label(__('Diagnóstico'))
                        ->rows(3),
                    Forms\Components\Textarea::make('work_performed')
                        ->label(__('Trabajo realizado'))
                        ->rows(3)
                        ->helperText(__('Obligatorio para poder completar la orden.')),
                    Forms\Components\Textarea::make('internal_notes')
                        ->label(__('Notas internas'))
                        ->rows(2),
                    Forms\Components\Textarea::make('customer_notes')
                        ->label(__('Observaciones para el cliente'))
                        ->rows(2)
                        ->helperText(__('Visible para el cliente en el portal')),
                ]),
            // Pedido 3: registrar el estado del vehículo al ingreso (rayones,
            // golpes) para evitar reclamos posteriores. Van al disco público,
            // que en prod es un volumen y sobrevive a los rebuilds de la imagen.
            Forms\Components\Section::make(__('Fotos del vehículo'))
                ->description(__('Sacá fotos del estado del auto al recibirlo: es el respaldo ante un reclamo.'))
                ->collapsible()
                ->schema([
                    Forms\Components\FileUpload::make('photos_in')
                        ->label(__('Al ingreso'))
                        ->multiple()
                        ->image()
                        ->reorderable()
                        ->appendFiles()
                        ->openable()
                        ->downloadable()
                        ->disk('public')
                        ->directory('work-orders/ingreso')
                        ->maxFiles(20)
                        ->maxSize(20480) // 20 MB: una foto de celular entra holgada
                        ->imagePreviewHeight('120')
                        ->panelLayout('grid')
                        ->helperText(__('Hasta 20 fotos. Sacalas antes de empezar a trabajar.'))
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('photos_out')
                        ->label(__('En la entrega'))
                        ->multiple()
                        ->image()
                        ->reorderable()
                        ->appendFiles()
                        ->openable()
                        ->downloadable()
                        ->disk('public')
                        ->directory('work-orders/entrega')
                        ->maxFiles(20)
                        ->maxSize(20480)
                        ->imagePreviewHeight('120')
                        ->panelLayout('grid')
                        ->helperText(__('Opcional: cómo se entregó el vehículo.'))
                        ->columnSpanFull(),
                ]),
            // Los puntos a trabajar los hereda del presupuesto: son los que quedaron
            // en REGULAR o MAL. Acá el mecánico marca si los hizo o no.
            Forms\Components\Section::make(__('Trabajo a realizar'))
                ->description(__('Puntos que el presupuesto marcó para trabajar. Si alguno no se pudo hacer, marcalo y explicá por qué.'))
                ->collapsible()
                ->schema([
                    Forms\Components\Repeater::make('checklist')
                        ->label('')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(12)
                        ->itemLabel(fn (array $state): string => trim(
                            '[' . ($state['categoria'] ?? '') . '] ' . ($state['nombre_item'] ?? '')
                        ))
                        ->schema([
                            Forms\Components\Hidden::make('id_punto'),
                            Forms\Components\Hidden::make('categoria'),
                            Forms\Components\Hidden::make('estado_presupuesto'),
                            Forms\Components\Hidden::make('observacion_previa'),

                            Forms\Components\TextInput::make('nombre_item')
                                ->label(__('Punto'))
                                ->disabled()
                                ->dehydrated() // lo usa el PDF
                                ->columnSpan(4),

                            Forms\Components\Placeholder::make('detalle_presupuesto')
                                ->label(__('Según el presupuesto'))
                                ->content(fn (Forms\Get $get): string => trim(
                                    ($get('estado_presupuesto') ?? '—') .
                                    ($get('observacion_previa') ? ' · ' . $get('observacion_previa') : '')
                                ))
                                ->columnSpan(3),

                            Forms\Components\Radio::make('estado')
                                ->label(__('¿Se hizo?'))
                                ->options(WorkOrder::PUNTO_ESTADOS)
                                ->inline()
                                ->live()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('aclaracion')
                                ->label(__('Por qué no se pudo'))
                                ->placeholder(__('Falta el repuesto, apareció otra falla...'))
                                // Obligatorio cuando el punto no se pudo hacer: es lo que
                                // pidió el cliente para saber qué pasó.
                                ->required(fn (Forms\Get $get): bool => $get('estado') === WorkOrder::PUNTO_NO_SE_PUDO)
                                ->visible(fn (Forms\Get $get): bool => $get('estado') === WorkOrder::PUNTO_NO_SE_PUDO)
                                ->columnSpan(3),
                        ]),
                ]),
            Forms\Components\Section::make(__('Ítems de la OS'))
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->label(__('Tipo'))
                                ->options(['labor' => __('Mano de obra'), 'part' => __('Pieza'), 'other' => __('Otro')])
                                ->default('labor')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                    // Cambiar de Pieza a otra cosa desvincula el repuesto,
                                    // para no descontar stock de un ítem que ya no lo es.
                                    if ($state !== 'part') {
                                        $set('inventory_item_id', null);
                                    }
                                }),
                            // Pedido 5: vincular la pieza al inventario para que la
                            // orden descuente stock al completarse.
                            Forms\Components\Select::make('inventory_item_id')
                                ->label(__('Repuesto del inventario'))
                                ->options(fn (): array => InventoryItem::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (InventoryItem $i): array => [
                                        $i->id => $i->name . ' (' . rtrim(rtrim((string) $i->stock_quantity, '0'), '.') . ' ' . $i->unit . ')',
                                    ])
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->visible(fn (Forms\Get $get): bool => $get('type') === 'part')
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                    if ($state && $item = InventoryItem::find($state)) {
                                        $set('description', $item->name);
                                        $set('unit_price', $item->sale_price);
                                    }
                                })
                                ->helperText(__('Opcional. Si lo vinculás, se descuenta del stock al completar la orden.')),
                            Forms\Components\TextInput::make('description')
                                ->label(__('Descripción'))
                                ->required(),
                            Forms\Components\TextInput::make('quantity')
                                ->label(__('Cant.'))
                                ->numeric()
                                ->default(1)
                                ->minValue(0.01),
                            Forms\Components\TextInput::make('unit_price')
                                ->label(__('Precio unit.'))
                                ->numeric()
                                ->prefix('$'),
                        ])
                        ->columns(4)
                        ->addActionLabel(__('Agregar ítem')),
                ]),
            Forms\Components\Section::make(__('Financiero'))
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('discount')
                        ->label(__('Descuento ($)'))
                        ->numeric()
                        ->default(0)
                        ->prefix('$'),
                    Forms\Components\Select::make('payment_method')
                        ->label(__('Forma de pago'))
                        ->options([
                            'cash'         => __('Efectivo'),
                            'credit_card'  => __('Tarjeta de Crédito'),
                            'debit_card'   => __('Tarjeta de Débito'),
                            'pix'          => __('PIX'),
                            'bank_slip'    => __('Boleto'),
                        ]),
                    Forms\Components\Select::make('payment_status')
                        ->label(__('Estado de pago'))
                        ->options([
                            'pending' => __('Pendiente'),
                            'partial' => __('Parcial'),
                            'paid'    => __('Pago'),
                        ])
                        ->default('pending'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('Nº OS'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Cliente'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle.license_plate')
                    ->label(__('Vehículo'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('mechanic.name')
                    ->label(__('Mecánico'))
                    ->placeholder(__('No asignado')),
                // El cliente pidió que se note cuando algo no se pudo hacer.
                Tables\Columns\IconColumn::make('novedades')
                    ->label(__('Novedades'))
                    ->icon(fn (WorkOrder $record): string => $record->isBlocked()
                        ? 'heroicon-o-lock-closed'
                        : ($record->hasIssues() ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check'))
                    ->color(fn (WorkOrder $record): string => $record->isBlocked()
                        ? 'danger'
                        : ($record->hasIssues() ? 'warning' : 'gray'))
                    ->tooltip(fn (WorkOrder $record): ?string => match (true) {
                        $record->isBlocked()  => __('Trabada: :motivo', ['motivo' => $record->blocked_reason]),
                        $record->hasIssues()  => __(':n punto(s) sin hacer', ['n' => count($record->issuePoints())]),
                        default               => null,
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('Estado')),
                Tables\Columns\BadgeColumn::make('priority')
                    ->label(__('Prioridad'))
                    ->colors([
                        'gray'    => 'low',
                        'primary' => 'normal',
                        'warning' => 'high',
                        'danger'  => 'urgent',
                    ]),
                Tables\Columns\TextColumn::make('total')
                    ->label(__('Total'))
                    ->money('ARS')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_at')
                    ->label(__('Previsión'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Apertura'))
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('con_novedades')
                    ->label(__('Con novedades'))
                    ->placeholder(__('Todas'))
                    ->trueLabel(__('Con algo sin hacer'))
                    ->falseLabel(__('Sin novedades'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('checklist', 'like', '%NO_SE_PUDO%'),
                        false: fn (Builder $query) => $query->where(fn (Builder $q) => $q
                            ->whereNull('checklist')
                            ->orWhere('checklist', 'not like', '%NO_SE_PUDO%')),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\TernaryFilter::make('trabadas')
                    ->label(__('Trabadas'))
                    ->placeholder(__('Todas'))
                    ->trueLabel(__('Solo trabadas'))
                    ->falseLabel(__('Sin trabas'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('blocked_reason'),
                        false: fn (Builder $query) => $query->whereNull('blocked_reason'),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Estado'))
                    // Derivado del enum: antes estaba hardcodeado y se desincronizaba.
                    ->options(fn (): array => collect(WorkOrderStatus::cases())
                        ->mapWithKeys(fn (WorkOrderStatus $s): array => [$s->value => $s->getLabel()])
                        ->all()),
                Tables\Filters\SelectFilter::make('mechanic_id')
                    ->label(__('Mecánico'))
                    ->options(fn () => Mechanic::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('Prioridad'))
                    ->options(['low' => __('Baja'), 'normal' => __('Normal'), 'high' => __('Alta'), 'urgent' => __('Urgente')]),
                Tables\Filters\Filter::make('overdue')
                    ->label(__('Atrasadas'))
                    ->query(fn(Builder $q) => $q
                        ->whereNotNull('estimated_at')
                        ->where('estimated_at', '<', now())
                        ->whereNotIn('status', ['completed', 'delivered'])
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label(__('PDF'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (WorkOrder $record): string => route('work-orders.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('advance_status')
                    ->label(__('Avanzar'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    // Solo lo ve quien puede hacer esa transición (regla del cliente:
                    // de completado a entregado solo lo pasa un comercial).
                    ->visible(function (WorkOrder $r): bool {
                        $next = WorkOrderStatus::nextStates($r->status)[0] ?? null;

                        return $next && WorkOrderTransitions::userCanMove(auth()->user(), $r->status, $next);
                    })
                    // Cada paso pide lo suyo: el mecánico al tomar la orden, la forma
                    // de pago al entregar.
                    ->form(function (WorkOrder $record): array {
                        $next = WorkOrderStatus::nextStates($record->status)[0] ?? null;

                        if ($next === WorkOrderStatus::Repairing) {
                            return [
                                Forms\Components\Select::make('mechanic_id')
                                    ->label(__('¿Quién se pone a trabajar?'))
                                    ->options(fn () => Mechanic::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                    ->default($record->mechanic_id)
                                    ->required(),
                            ];
                        }

                        if ($next === WorkOrderStatus::Delivered && ! $record->isFree()) {
                            $saldo = $record->balance();

                            return [
                                Forms\Components\Placeholder::make('resumen')
                                    ->label(__('A cobrar'))
                                    ->content(fn (): string => '$ ' . number_format($saldo, 2, ',', '.')
                                        . ($record->totalPaid() > 0
                                            ? ' (' . __('ya pagó') . ' $ ' . number_format($record->totalPaid(), 2, ',', '.') . ')'
                                            : '')),
                                Forms\Components\TextInput::make('amount')
                                    ->label(__('Monto cobrado'))
                                    ->numeric()
                                    ->prefix('$')
                                    ->default($saldo)
                                    ->required(),
                                Forms\Components\Select::make('method')
                                    ->label(__('Forma de pago'))
                                    ->options(Payment::METHODS)
                                    ->required(),
                                Forms\Components\Textarea::make('notes')
                                    ->label(__('Observaciones'))
                                    ->rows(2),
                            ];
                        }

                        return [];
                    })
                    ->action(function (WorkOrder $record, array $data) {
                        $next = WorkOrderStatus::nextStates($record->status)[0];

                        // Pedido 6: si el cierre deja stock en negativo se avisa, pero
                        // no se bloquea: la pieza ya se usó, ocultarlo haría que el
                        // inventario mienta. El aviso es para que alguien lo corrija.
                        $faltantes = $next === WorkOrderStatus::Completed
                            ? app(WorkOrderStockService::class)->shortages($record)
                            : [];

                        try {
                            app(UpdateWorkOrderStatusAction::class)->execute($record, $next, options: [
                                'mechanic_id' => $data['mechanic_id'] ?? null,
                                'payment'     => isset($data['amount']) ? [
                                    'amount' => (float) $data['amount'],
                                    'method' => $data['method'],
                                    'type'   => 'saldo',
                                    'notes'  => $data['notes'] ?? null,
                                ] : null,
                            ]);
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->title(__('No se puede avanzar'))
                                ->body($e->getMessage())
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title("OS avanzada a: {$next->getLabel()}")
                            ->success()
                            ->send();

                        if ($faltantes !== []) {
                            $detalle = collect($faltantes)
                                ->map(fn (array $f): string => $f['item']->description
                                    . ' (' . __('necesita') . ' ' . $f['necesario'] . ', ' . __('hay') . ' ' . $f['disponible'] . ')')
                                ->implode('; ');

                            Notification::make()
                                ->title(__('Stock en negativo'))
                                ->body(__('Se descontó igual, pero revisá el inventario: :detalle', ['detalle' => $detalle]))
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('download_pdfs_zip')
                        ->label(__('Descargar PDFs (ZIP)'))
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('gray')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => app(BulkPdfZipService::class)->downloadWorkOrders($records)),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('OS'))
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('number')->label(__('Número'))->weight('bold'),
                    Infolists\Components\TextEntry::make('status')->label(__('Estado'))->badge(),
                    Infolists\Components\TextEntry::make('priority')->label(__('Prioridad'))->badge(),
                    Infolists\Components\TextEntry::make('customer.name')->label(__('Cliente')),
                    Infolists\Components\TextEntry::make('vehicle.display_name')->label(__('Vehículo')),
                    Infolists\Components\TextEntry::make('mechanic.name')->label(__('Mecánico'))->placeholder(__('No asignado')),
                    Infolists\Components\TextEntry::make('complaint')->label(__('Queja'))->columnSpan(3),
                    Infolists\Components\TextEntry::make('diagnosis')->label(__('Diagnóstico'))->columnSpan(3)->placeholder('—'),
                    // Pedido 3: las fotos del ingreso son la prueba ante un reclamo,
                    // así que tienen que verse sin entrar a editar la orden.
                    Infolists\Components\ImageEntry::make('photos_in')
                        ->label(__('Fotos al ingreso'))
                        ->disk('public')
                        ->height(110)
                        ->square()
                        ->columnSpan(3)
                        ->placeholder(__('Sin fotos de ingreso'))
                        ->visible(fn ($record): bool => filled($record->photos_in)),
                    Infolists\Components\ImageEntry::make('photos_out')
                        ->label(__('Fotos en la entrega'))
                        ->disk('public')
                        ->height(110)
                        ->square()
                        ->columnSpan(3)
                        ->visible(fn ($record): bool => filled($record->photos_out)),
                    Infolists\Components\RepeatableEntry::make('checklist')
                        ->label(__('Checklist de revisión'))
                        ->schema([
                            Infolists\Components\TextEntry::make('item')->label(__('Ítem')),
                            Infolists\Components\IconEntry::make('done')
                                ->label(__('Revisado'))
                                ->boolean(),
                            Infolists\Components\TextEntry::make('note')
                                ->label(__('Observaciones'))
                                ->placeholder('—'),
                        ])
                        ->columns(3)
                        ->columnSpan(3),
                ]),
            Infolists\Components\Section::make(__('Financiero'))
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('labor_cost')->label(__('Mano de obra'))->money('ARS'),
                    Infolists\Components\TextEntry::make('parts_cost')->label(__('Piezas'))->money('ARS'),
                    Infolists\Components\TextEntry::make('discount')->label(__('Descuento'))->money('ARS'),
                    Infolists\Components\TextEntry::make('total')->label(__('Total'))->money('ARS')->weight('bold'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWorkOrders::route('/'),
            'create' => Pages\CreateWorkOrder::route('/create'),
            'view'   => Pages\ViewWorkOrder::route('/{record}'),
            'edit'   => Pages\EditWorkOrder::route('/{record}/edit'),
        ];
    }
}
