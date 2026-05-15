<?php

namespace App\Filament\Resources;

use App\Actions\WorkOrder\UpdateWorkOrderStatusAction;
use App\Enums\WorkOrderStatus;
use App\Filament\Pages\WorkOrdersBoard;
use App\Filament\Resources\WorkOrderResource\Pages;
use App\Services\Pdf\BulkPdfZipService;
use App\Models\Customer;
use App\Models\Mechanic;
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
    protected static ?string $navigationGroup = 'Taller';
    protected static ?string $modelLabel = 'Orden de Servicio';
    protected static ?string $pluralModelLabel = 'Órdenes de Servicio';
    protected static ?int $navigationSort = 1;

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
            Forms\Components\Section::make('Identificación')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('Cliente')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn($set) => $set('vehicle_id', null)),
                    Forms\Components\Select::make('vehicle_id')
                        ->label('Vehículo')
                        ->options(function (Forms\Get $get) {
                            $customerId = $get('customer_id');
                            if (! $customerId) return [];
                            return Vehicle::where('customer_id', $customerId)
                                ->get()
                                ->mapWithKeys(fn($v) => [$v->id => $v->display_name]);
                        })
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('mechanic_id')
                        ->label('Mecánico')
                        ->relationship(
                            name: 'mechanic',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                        )
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Forms\Components\Select::make('priority')
                        ->label('Prioridad')
                        ->options([
                            'low'    => 'Baja',
                            'normal' => 'Normal',
                            'high'   => 'Alta',
                            'urgent' => 'Urgente',
                        ])
                        ->default('normal')
                        ->required(),
                    Forms\Components\TextInput::make('mileage_in')
                        ->label('KM Entrada')
                        ->numeric(),
                    Forms\Components\DateTimePicker::make('estimated_at')
                        ->label('Previsión de Entrega'),
                ]),
            Forms\Components\Section::make('Descripción')
                ->schema([
                    Forms\Components\Textarea::make('complaint')
                        ->label('Queja del cliente')
                        ->required()
                        ->rows(3),
                    Forms\Components\Textarea::make('diagnosis')
                        ->label('Diagnóstico')
                        ->rows(3),
                    Forms\Components\Textarea::make('internal_notes')
                        ->label('Notas internas')
                        ->rows(2),
                    Forms\Components\Textarea::make('customer_notes')
                        ->label('Observaciones para el cliente')
                        ->rows(2)
                        ->helperText('Visible para el cliente en el portal'),
                ]),
            Forms\Components\Section::make('Checklist de revisión')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Repeater::make('checklist')
                        ->label('Puntos a revisar')
                        ->schema([
                            Forms\Components\TextInput::make('item')
                                ->label('Ítem')
                                ->required()
                                ->maxLength(120),
                            Forms\Components\Toggle::make('done')
                                ->label('Revisado')
                                ->default(false),
                            Forms\Components\TextInput::make('note')
                                ->label('Observaciones')
                                ->maxLength(255),
                        ])
                        ->columns(3)
                        ->default([
                            ['item' => 'Luces y señales', 'done' => false, 'note' => null],
                            ['item' => 'Nivel de fluidos', 'done' => false, 'note' => null],
                            ['item' => 'Frenos', 'done' => false, 'note' => null],
                            ['item' => 'Presión de neumáticos', 'done' => false, 'note' => null],
                        ])
                        ->addActionLabel('Agregar punto'),
                ]),
            Forms\Components\Section::make('Ítems de la OS')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->label('Tipo')
                                ->options(['labor' => 'Mano de obra', 'part' => 'Pieza', 'other' => 'Otro'])
                                ->default('labor')
                                ->required(),
                            Forms\Components\TextInput::make('description')
                                ->label('Descripción')
                                ->required(),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Cant.')
                                ->numeric()
                                ->default(1)
                                ->minValue(0.01),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Precio unit.')
                                ->numeric()
                                ->prefix('$'),
                        ])
                        ->columns(4)
                        ->addActionLabel('Agregar ítem'),
                ]),
            Forms\Components\Section::make('Financiero')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('discount')
                        ->label('Descuento ($)')
                        ->numeric()
                        ->default(0)
                        ->prefix('$'),
                    Forms\Components\Select::make('payment_method')
                        ->label('Forma de pago')
                        ->options([
                            'cash'         => 'Efectivo',
                            'credit_card'  => 'Tarjeta de Crédito',
                            'debit_card'   => 'Tarjeta de Débito',
                            'pix'          => 'PIX',
                            'bank_slip'    => 'Boleto',
                        ]),
                    Forms\Components\Select::make('payment_status')
                        ->label('Estado de pago')
                        ->options([
                            'pending' => 'Pendiente',
                            'partial' => 'Parcial',
                            'paid'    => 'Pago',
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
                    ->label('Nº OS')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle.license_plate')
                    ->label('Vehículo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mechanic.name')
                    ->label('Mecánico')
                    ->placeholder('No asignado'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado'),
                Tables\Columns\BadgeColumn::make('priority')
                    ->label('Prioridad')
                    ->colors([
                        'gray'    => 'low',
                        'primary' => 'normal',
                        'warning' => 'high',
                        'danger'  => 'urgent',
                    ]),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_at')
                    ->label('Previsión')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'received' => 'Recibido',
                        'diagnosis' => 'Diagnóstico',
                        'waiting_parts' => 'Esperando piezas',
                        'repairing' => 'En reparación',
                        'completed' => 'Completado',
                        'delivered' => 'Entregado',
                    ]),
                Tables\Filters\SelectFilter::make('mechanic_id')
                    ->label('Mecánico')
                    ->options(fn () => Mechanic::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente']),
                Tables\Filters\Filter::make('overdue')
                    ->label('Atrasadas')
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
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (WorkOrder $record): string => route('work-orders.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('advance_status')
                    ->label('Avanzar')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(WorkOrder $r) => ! empty(WorkOrderStatus::nextStates($r->status)))
                    ->action(function (WorkOrder $record) {
                        $next = WorkOrderStatus::nextStates($record->status)[0];
                        app(UpdateWorkOrderStatusAction::class)->execute($record, $next);
                        Notification::make()
                            ->title("OS avanzada a: {$next->getLabel()}")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('download_pdfs_zip')
                        ->label('Descargar PDFs (ZIP)')
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
            Infolists\Components\Section::make('OS')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('number')->label('Número')->weight('bold'),
                    Infolists\Components\TextEntry::make('status')->label('Estado')->badge(),
                    Infolists\Components\TextEntry::make('priority')->label('Prioridad')->badge(),
                    Infolists\Components\TextEntry::make('customer.name')->label('Cliente'),
                    Infolists\Components\TextEntry::make('vehicle.display_name')->label('Vehículo'),
                    Infolists\Components\TextEntry::make('mechanic.name')->label('Mecánico')->placeholder('No asignado'),
                    Infolists\Components\TextEntry::make('complaint')->label('Queja')->columnSpan(3),
                    Infolists\Components\TextEntry::make('diagnosis')->label('Diagnóstico')->columnSpan(3)->placeholder('—'),
                    Infolists\Components\RepeatableEntry::make('checklist')
                        ->label('Checklist de revisión')
                        ->schema([
                            Infolists\Components\TextEntry::make('item')->label('Ítem'),
                            Infolists\Components\IconEntry::make('done')
                                ->label('Revisado')
                                ->boolean(),
                            Infolists\Components\TextEntry::make('note')
                                ->label('Observaciones')
                                ->placeholder('—'),
                        ])
                        ->columns(3)
                        ->columnSpan(3),
                ]),
            Infolists\Components\Section::make('Financiero')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('labor_cost')->label('Mano de obra')->money('ARS'),
                    Infolists\Components\TextEntry::make('parts_cost')->label('Piezas')->money('ARS'),
                    Infolists\Components\TextEntry::make('discount')->label('Descuento')->money('ARS'),
                    Infolists\Components\TextEntry::make('total')->label('Total')->money('ARS')->weight('bold'),
                ]),
        ]);
    }

    public static function getRelationManagers(): array
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
