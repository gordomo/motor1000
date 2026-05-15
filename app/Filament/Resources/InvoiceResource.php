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
    protected static ?string $navigationGroup      = 'Financiero';
    protected static ?string $navigationLabel      = 'Facturas';
    protected static ?string $modelLabel           = 'Factura';
    protected static ?string $pluralModelLabel     = 'Facturas';
    protected static ?int    $navigationSort       = 1;

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
            Forms\Components\Section::make('Información de Factura')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->label('Cliente'),

                    Forms\Components\Select::make('work_order_id')
                        ->relationship('workOrder', 'number')
                        ->searchable()
                        ->nullable()
                        ->label('Orden de Servicio'),

                    Forms\Components\Select::make('status')
                        ->options([
                            'draft'    => 'Borrador',
                            'pending'  => 'Pendiente',
                            'paid'     => 'Pago',
                            'overdue'  => 'Vencido',
                            'canceled' => 'Cancelado',
                        ])
                        ->required()
                        ->default('pending')
                        ->label('Estado'),
                ]),

            Forms\Components\Section::make('Valores')
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('subtotal')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->default(0)
                        ->label('Subtotal'),

                    Forms\Components\TextInput::make('tax')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->label('Impuestos'),

                    Forms\Components\TextInput::make('discount')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->label('Descuento'),

                    Forms\Components\TextInput::make('total')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->default(0)
                        ->label('Total'),
                ]),

            Forms\Components\Section::make('Pago')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('payment_method')
                        ->options([
                            'cash'         => 'Efectivo',
                            'credit_card'  => 'Tarjeta de Crédito',
                            'debit_card'   => 'Tarjeta de Débito',
                            'pix'          => 'PIX',
                            'bank_transfer'=> 'Transferencia',
                            'check'        => 'Cheque',
                        ])
                        ->nullable()
                        ->label('Forma de pago'),

                    Forms\Components\DateTimePicker::make('due_at')
                        ->label('Vencimiento'),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Fecha de pago'),
                ]),

            Forms\Components\Textarea::make('notes')
                ->rows(3)
                ->columnSpanFull()
                ->label('Observaciones'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable()
                    ->label('Número'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->label('Cliente'),

                Tables\Columns\TextColumn::make('workOrder.number')
                    ->label('OS')
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning'   => 'pending',
                        'success'   => 'paid',
                        'danger'    => fn ($state) => in_array($state, ['overdue', 'canceled']),
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft'    => 'Borrador',
                        'pending'  => 'Pendiente',
                        'paid'     => 'Pago',
                        'overdue'  => 'Vencido',
                        'canceled' => 'Cancelado',
                        default    => $state,
                    })
                    ->label('Estado'),

                Tables\Columns\TextColumn::make('total')
                    ->money('ARS')
                    ->sortable()
                    ->label('Total'),

                Tables\Columns\TextColumn::make('due_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->label('Vencimiento'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->label('Pagado el'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'    => 'Borrador',
                        'pending'  => 'Pendiente',
                        'paid'     => 'Pago',
                        'overdue'  => 'Vencido',
                        'canceled' => 'Cancelado',
                    ])
                    ->label('Estado'),

                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidas')
                    ->query(fn (Builder $q) => $q->where('status', 'overdue')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Invoice $record): string => route('invoices.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('mark_paid')
                    ->label('Marcar como pagada')
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
                        ->label('Descargar PDFs (ZIP)')
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
            Infolists\Components\Section::make('Factura')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('number')->label('Número'),
                    Infolists\Components\TextEntry::make('customer.name')->label('Cliente'),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->label('Estado'),
                    Infolists\Components\TextEntry::make('subtotal')->money('ARS')->label('Subtotal'),
                    Infolists\Components\TextEntry::make('tax')->money('ARS')->label('Impuestos'),
                    Infolists\Components\TextEntry::make('discount')->money('ARS')->label('Descuento'),
                    Infolists\Components\TextEntry::make('total')->money('ARS')->label('Total'),
                    Infolists\Components\TextEntry::make('due_at')->dateTime('d/m/Y')->label('Vencimiento'),
                    Infolists\Components\TextEntry::make('paid_at')->dateTime('d/m/Y')->placeholder('—')->label('Pagado el'),
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
