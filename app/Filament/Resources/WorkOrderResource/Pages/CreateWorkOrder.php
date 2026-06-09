<?php

namespace App\Filament\Resources\WorkOrderResource\Pages;

use App\Filament\Resources\WorkOrderResource;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Support\CurrentTenant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var WorkOrder $record */
        $record = parent::handleRecordCreation($data);

        $record->vehicle?->update(['last_service_at' => now()]);
        $record->customer?->update(['last_visit_at' => now()]);

        return $record;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = $this->resolveTenant()->id;
        $data['status'] = $data['status'] ?? 'received';
        $data['discount'] = $data['discount'] ?? 0;
        $data['payment_status'] = $data['payment_status'] ?? 'pending';

        return $data;
    }

    private function resolveTenant(): Tenant
    {
        $tenant = CurrentTenant::get();

        abort_if(! $tenant, 403);

        return $tenant;
    }
}
