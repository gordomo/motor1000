<?php

namespace App\Filament\Widgets\ParaHoy;

use App\Filament\Resources\ReminderResource;
use App\Models\Reminder;
use App\Support\Etiquetas;
use App\Support\ParaHoy;
use App\Support\WhatsAppLink;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Pestaña "Recordatorios" de Para hoy: los que vencen hoy o ya vencieron. */
class RecordatoriosHoy extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(ParaHoy::recordatorios()->with(['customer', 'vehicle']))
            ->heading(null)
            ->defaultSort('due_at')
            ->emptyStateIcon('heroicon-o-bell')
            ->emptyStateHeading(__('No hay recordatorios para hoy'))
            ->emptyStateDescription(__('Aparecen acá el día que vencen, hasta que los marques como hechos o los descartes.'))
            ->columns([
                Tables\Columns\TextColumn::make('due_at')
                    ->label(__('Vence'))
                    ->date('d/m')
                    ->color(fn (Reminder $record): ?string => $record->due_at->isBefore(today()) ? 'danger' : null)
                    ->description(fn (Reminder $record): ?string => $record->due_at->isBefore(today()) ? __('Vencido') : null)
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Cliente'))
                    ->weight('bold')
                    ->description(fn (Reminder $record): string => collect([
                        // En el celular la columna "Vence" no está: la fecha va acá.
                        $record->due_at->isBefore(today()) ? __('Vencido :fecha', ['fecha' => $record->due_at->format('d/m')]) : null,
                        $record->title,
                        $record->vehicle?->license_plate,
                    ])->filter()->implode(' · ')),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Etiquetas::tipoRecordatorio($state))
                    ->visibleFrom('lg'),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label(__('WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->url(fn (Reminder $record): ?string => self::link($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Reminder $record): bool => self::link($record) !== null),
                Tables\Actions\Action::make('hecho')
                    ->label(__('Hecho'))
                    ->icon('heroicon-o-check')
                    ->button()
                    ->action(function (Reminder $record): void {
                        $record->update(['status' => 'completed']);
                        $this->dispatch('para-hoy-actualizado');
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('descartar')
                        ->label(__('Descartar'))
                        ->icon('heroicon-o-x-mark')
                        ->requiresConfirmation()
                        ->modalHeading(__('¿Descartar este recordatorio?'))
                        ->modalDescription(__('Deja de aparecer en Para hoy. Queda guardado como descartado.'))
                        ->action(function (Reminder $record): void {
                            $record->update(['status' => 'dismissed']);
                            $this->dispatch('para-hoy-actualizado');
                        }),
                    Tables\Actions\Action::make('ver')
                        ->label(__('Abrir'))
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Reminder $record): string => ReminderResource::getUrl('edit', ['record' => $record])),
                ]),
            ]);
    }

    private static function link(Reminder $r): ?string
    {
        $c = $r->customer;

        if (! $c?->whatsapp_opted_in) {
            return null;
        }

        return WhatsAppLink::para($c->whatsapp ?: $c->phone, ParaHoy::mensajeRecordatorio($r));
    }
}
