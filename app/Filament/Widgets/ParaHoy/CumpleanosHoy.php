<?php

namespace App\Filament\Widgets\ParaHoy;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\ParaHoy;
use App\Support\WhatsAppLink;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Pestaña "Cumpleaños" de Para hoy. */
class CumpleanosHoy extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(ParaHoy::cumpleanos())
            ->heading(null)
            ->paginated(false)
            ->defaultSort('name')
            ->emptyStateIcon('heroicon-o-cake')
            ->emptyStateHeading(__('Hoy no cumple años ningún cliente'))
            ->emptyStateDescription(__('Se ven los clientes que tienen cargada la fecha de nacimiento.'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Cliente'))
                    ->weight('bold')
                    ->description(fn (Customer $record): string => trans_choice(
                        '{1} Cumple 1 año|[0,*] Cumple :count años',
                        $record->birthday->age,
                        ['count' => $record->birthday->age],
                    )),
                Tables\Columns\TextColumn::make('whatsapp')
                    ->label(__('WhatsApp / Teléfono'))
                    ->state(fn (Customer $record): ?string => $record->whatsapp ?: $record->phone)
                    ->placeholder(__('Sin teléfono'))
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('last_visit_at')
                    ->label(__('Última visita'))
                    ->date('d/m/Y')
                    ->placeholder(__('Nunca'))
                    ->visibleFrom('lg'),
                Tables\Columns\IconColumn::make('atendido')
                    ->label(__('Atendido'))
                    ->state(fn (Customer $record): bool => self::atendido($record))
                    ->boolean()
                    // En el celular sobra: el botón "Listo" aparece solo si falta.
                    ->visibleFrom('md'),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label(__('Saludar'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->url(fn (Customer $record): ?string => self::link($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Customer $record): bool => self::link($record) !== null),
                Tables\Actions\Action::make('listo')
                    ->label(__('Listo'))
                    ->icon('heroicon-o-check')
                    ->button()
                    ->visible(fn (Customer $record): bool => ! self::atendido($record))
                    ->action(function (Customer $record): void {
                        $record->update(['birthday_contacted_at' => now()]);
                        $this->dispatch('para-hoy-actualizado');
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('ver')
                        ->label(__('Ver cliente'))
                        ->icon('heroicon-o-user')
                        ->url(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record])),
                ]),
            ]);
    }

    private static function atendido(Customer $c): bool
    {
        return (bool) $c->birthday_contacted_at?->isSameYear(now());
    }

    private static function link(Customer $c): ?string
    {
        if (! $c->whatsapp_opted_in) {
            return null;
        }

        return WhatsAppLink::para($c->whatsapp ?: $c->phone, ParaHoy::mensajeCumpleanos($c));
    }
}
