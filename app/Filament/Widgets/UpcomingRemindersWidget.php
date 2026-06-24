<?php

namespace App\Filament\Widgets;

use App\Models\Reminder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingRemindersWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('Recordatorios Próximos');
    }

    public function table(Table $table): Table
    {
        $tenantId = \App\Support\CurrentTenant::id() ?? 0;

        return $table
            ->query(
                Reminder::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->where('due_at', '<=', now()->addDays(30))
                    ->with(['customer', 'vehicle'])
                    ->orderBy('due_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')->label(__('Cliente'))->searchable(),
                Tables\Columns\TextColumn::make('vehicle.license_plate')->label(__('Vehículo'))->placeholder('—'),
                Tables\Columns\BadgeColumn::make('type')->label(__('Tipo')),
                Tables\Columns\TextColumn::make('title')->label(__('Recordatorio')),
                Tables\Columns\TextColumn::make('due_at')->label(__('Vencimiento'))->dateTime('d/m/Y')->sortable(),
            ]);
    }
}
