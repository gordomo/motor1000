<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Pages\AppointmentsCalendar;
use App\Filament\Resources\AppointmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calendar')
                ->label('Ver calendario')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(AppointmentsCalendar::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
