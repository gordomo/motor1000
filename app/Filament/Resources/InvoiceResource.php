<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Services\Pdf\BulkPdfZipService;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon       = 'heroicon-o-document-currency-dollar';
    protected static ?int    $navigationSort       = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Financiero');
    }

    public static function getNavigationLabel(): string
    {
        return __('Facturas');
    }

    public static function getModelLabel(): string
    {
        return __('Factura');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Facturas');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Invoice::whereIn('status', ['pending', 'overdue'])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Invoice::where('status', 'overdue')->exists() ? 'danger' : 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Información de Factura'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('work_order_id', null))
                        ->label(__('Cliente')),

                    // Falla #6: el desplegable se llena con las órdenes del cliente
                    // elegido y, al seleccionar una, autocompleta los importes.
                    Forms\Components\Select::make('work_order_id')
                        ->label(__('Orden de Servicio'))
                        ->options(function (Forms\Get $get): array {
                            $customerId = $get('customer_id');
                            if (! $customerId) {
                                return [];
                            }
                            return \App\Models\WorkOrder::query()
                                ->where('customer_id', $customerId)
                                ->orderByDesc('id')
                                ->pluck('number', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->nullable()
                        ->reactive()
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            if (! $state) {
                                return;
                            }
                            $order = \App\Models\WorkOrder::query()->find($state);
                            if (! $order) {
                                return;
                            }
                            $subtotal = (float) $order->labor_cost + (float) $order->parts_cost;
                            $discount = (float) $order->discount;
                            $set('subtotal', round($subtotal, 2));
                            $set('discount', round($discount, 2));
                            $set('total', round(max(0, $subtotal - $discount), 2));
                        }),

                    Forms\Components\Select::make('status')
                        ->options([
                            'draft'    => __('Borrador'),
                            'pending'  => __('Pendiente'),
                            'paid'     => __('Pago'),
                            'overdue'  => __('Vencido'),
                            'canceled' => __('Cancelado'),
                        ])
                        ->required()
                        ->default('pending')
                        ->label(__('Estado')),
                ]),

            Forms\Components\Section::make(__('Valores'))
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('subtotal')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->default(0)
                        ->label(__('Subtotal')),

                    Forms\Components\TextInput::make('tax')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->label(__('Impuestos')),

                    Forms\Components\TextInput::make('discount')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->label(__('Descuento')),

                    Forms\Components\TextInput::make('total')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->default(0)
                        ->label(__('Total')),
                ]),

            Forms\Components\Section::make(__('Pago'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('payment_method')
                        ->options([
                            'cash'          => __('Efectivo'),
                            'credit_card'   => __('Tarjeta de Crédito'),
                            'debit_card'    => __('Tarjeta de Débito'),
                            'bank_transfer' => __('Transferencia bancaria'),
                            'mercado_pago'  => __('Mercado Pago'),
                            'check'         => __('Cheque'),
                        ])
                        ->nullable()
                        ->label(__('Forma de pago')),

                    Forms\Components\DateTimePicker::make('due_at')
                        ->label(__('Vencimiento')),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label(__('Fecha de pago')),
                ]),

            Forms\Components\Textarea::make('notes')
                ->rows(3)
                ->columnSpanFull()
                ->label(__('Observaciones')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable()
                    ->label(__('Número')),

                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->label(__('Cliente')),

                Tables\Columns\TextColumn::make('workOrder.number')
                    ->label(__('OS'))
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning'   => 'pending',
                        'success'   => 'paid',
                        'danger'    => fn ($state) => in_array($state, ['overdue', 'canceled']),
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'    => __('Borrador'),
                        'pending'  => __('Pendiente'),
                        'paid'     => __('Pago'),
                        'overdue'  => __('Vencido'),
                        'canceled' => __('Cancelado'),
                        default    => $state,
                    })
                    ->label(__('Estado')),

                Tables\Columns\TextColumn::make('total')
                    ->money('ARS')
                    ->sortable()
                    ->label(__('Total')),

                Tables\Columns\TextColumn::make('due_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->label(__('Vencimiento')),

                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->label(__('Pagado el')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'    => __('Borrador'),
                        'pending'  => __('Pendiente'),
                        'paid'     => __('Pago'),
                        'overdue'  => __('Vencido'),
                        'canceled' => __('Cancelado'),
                    ])
                    ->label(__('Estado')),

                Tables\Filters\Filter::make('overdue')
                    ->label(__('Vencidas'))
                    ->query(fn (Builder $q) => $q->where('status', 'overdue')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label(__('PDF'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Invoice $record): string => route('invoices.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('mark_paid')
                    ->label(__('Marcar como pagada'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Invoice $r) => in_array($r->status, ['pending', 'overdue']))
                    ->requiresConfirmation()
                    ->action(fn (Invoice $r) => $r->update([
                        'status'  => 'paid',
                        'paid_at' => now(),
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('download_pdfs_zip')
                        ->label(__('Descargar PDFs (ZIP)'))
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('gray')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => app(BulkPdfZipService::class)->downloadInvoices($records)),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Factura'))
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('number')->label(__('Número')),
                    Infolists\Components\TextEntry::make('customer.name')->label(__('Cliente')),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->label(__('Estado')),
                    Infolists\Components\TextEntry::make('subtotal')->money('ARS')->label(__('Subtotal')),
                    Infolists\Components\TextEntry::make('tax')->money('ARS')->label(__('Impuestos')),
                    Infolists\Components\TextEntry::make('discount')->money('ARS')->label(__('Descuento')),
                    Infolists\Components\TextEntry::make('total')->money('ARS')->label(__('Total')),
                    Infolists\Components\TextEntry::make('due_at')->dateTime('d/m/Y')->label(__('Vencimiento')),
                    Infolists\Components\TextEntry::make('paid_at')->dateTime('d/m/Y')->placeholder('—')->label(__('Pagado el')),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view'   => Pages\ViewInvoice::route('/{record}'),
            'edit'   => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
