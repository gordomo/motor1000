<?php

namespace App\Filament\Resources\ReminderResource\Pages;

use App\Filament\Resources\ReminderResource;
use App\Models\Reminder;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListReminders extends ListRecords
{
    protected static string $resource = ReminderResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas')
                ->badge(fn (): int => Reminder::query()->count()),
            'pending' => Tab::make('Pendiente')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn (): int => Reminder::query()->where('status', 'pending')->count()),
            'sent' => Tab::make('Enviado')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'sent'))
                ->badge(fn (): int => Reminder::query()->where('status', 'sent')->count()),
            'dismissed' => Tab::make('Descartado')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'dismissed'))
                ->badge(fn (): int => Reminder::query()->where('status', 'dismissed')->count()),
            'completed' => Tab::make('Completado')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(fn (): int => Reminder::query()->where('status', 'completed')->count()),
        ];
    }
}
