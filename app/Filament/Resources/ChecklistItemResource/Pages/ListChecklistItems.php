<?php

namespace App\Filament\Resources\ChecklistItemResource\Pages;

use App\Filament\Resources\ChecklistItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChecklistItems extends ListRecords
{
    protected static string $resource = ChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label(__('Nuevo punto')),
        ];
    }
}
