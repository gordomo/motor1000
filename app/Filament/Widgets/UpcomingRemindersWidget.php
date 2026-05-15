<?php

namespace App\Filament\Widgets;

use App\Models\Reminder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingRemindersWidget extends BaseWidget
{
    protected static ?string $heading = 'Recordatorios Próximos';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenantId = app('current.tenant')?->id ?? 0;

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
                Tables\Columns\TextColumn::make('customer.name')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('vehicle.license_plate')->label('Vehículo')->placeholder('—'),
                Tables\Columns\BadgeColumn::make('type')->label('Tipo'),
                Tables\Columns\TextColumn::make('title')->label('Recordatorio'),
                Tables\Columns\TextColumn::make('due_at')->label('Vencimiento')->dateTime('d/m/Y')->sortable(),
            ]);
    }
}
