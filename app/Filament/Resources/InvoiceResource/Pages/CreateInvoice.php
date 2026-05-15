<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = app('current.tenant');
        $data['tenant_id'] = $tenant->id;

        $last = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->max('number');

        $data['number'] = $last
            ? 'INV-' . str_pad((intval(ltrim(str_replace('INV-', '', $last), '0')) + 1), 5, '0', STR_PAD_LEFT)
            : 'INV-00001';

        return $data;
    }
}
