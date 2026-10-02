<?php

namespace App\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\CommunicationTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunicationTemplate extends CreateRecord
{
    use ConBotonVolver;

    protected static string $resource = CommunicationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = \App\Support\CurrentTenant::id();
        // Identificador interno (único por taller): ya no se pide en el formulario.
        $data['slug'] ??= \App\Support\Mensajes::slugNuevo($data['event'] ?? 'mensaje', $data['channel'] ?? '');

        return $data;
    }
}
