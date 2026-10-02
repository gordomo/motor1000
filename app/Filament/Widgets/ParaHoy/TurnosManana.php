<?php

namespace App\Filament\Widgets\ParaHoy;

use App\Filament\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Support\Etiquetas;
use App\Support\ParaHoy;
use App\Support\WhatsAppLink;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Pestaña "Turnos de mañana" de Para hoy: para confirmarlos con el cliente. */
class TurnosManana extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(ParaHoy::turnosManana()->with(['customer', 'vehicle']))
            ->heading(null)
            ->paginated(false)
            ->defaultSort('scheduled_at')
            ->emptyStateIcon('heroicon-o-calendar')
            ->emptyStateHeading(__('Mañana no hay turnos'))
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')->label(__('Hora'))->dateTime('H:i')->weight('bold'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Cliente'))
                    ->description(fn (Appointment $record): string => collect([$record->title, $record->vehicle?->license_plate])->filter()->implode(' · ')),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (Appointment $record): string => self::confirmado($record) ? 'success' : 'warning')
                    ->formatStateUsing(fn (Appointment $record): string => self::confirmado($record)
                        ? __('Confirmado')
                        : __('Sin confirmar'))
                    // En el celular sobra: el botón "Confirmó" aparece solo si falta.
                    ->visibleFrom('md'),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label(__('Escribirle'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->url(fn (Appointment $record): ?string => self::link($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Appointment $record): bool => ! self::confirmado($record) && self::link($record) !== null),
                Tables\Actions\Action::make('confirmado')
                    ->label(__('Confirmó'))
                    ->icon('heroicon-o-check')
                    ->button()
                    ->visible(fn (Appointment $record): bool => ! self::confirmado($record))
                    ->action(function (Appointment $record): void {
                        $record->update(['status' => 'confirmed', 'client_confirmed_at' => now()]);
                        $this->dispatch('para-hoy-actualizado');
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('ver')
                        ->label(__('Abrir turno'))
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Appointment $record): string => AppointmentResource::getUrl('edit', ['record' => $record])),
                ]),
            ]);
    }

    private static function confirmado(Appointment $a): bool
    {
        return $a->status !== 'scheduled' || $a->client_confirmed_at !== null;
    }

    private static function link(Appointment $a): ?string
    {
        $c = $a->customer;

        if (! $c?->whatsapp_opted_in) {
            return null;
        }

        return WhatsAppLink::para($c->whatsapp ?: $c->phone, ParaHoy::mensajeTurno($a));
    }
}
