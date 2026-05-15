<?php

namespace App\Actions\Customer;

use App\Models\Customer;
use App\Models\Reminder;
use App\Services\ReminderService;
use Carbon\Carbon;

class CreateMaintenanceReminderAction
{
    public function __construct(
        private readonly ReminderService $reminderService,
    ) {}

    public function execute(Customer $customer, array $data): Reminder
    {
        return $this->reminderService->create([
            'tenant_id'    => $customer->tenant_id,
            'customer_id'  => $customer->id,
            'vehicle_id'   => $data['vehicle_id'] ?? null,
            'type'         => $data['type'],
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'trigger_type' => $data['trigger_type'] ?? 'date',
            'due_at'       => $data['due_at'] ?? null,
            'due_mileage'  => $data['due_mileage'] ?? null,
            'status'       => 'pending',
        ]);
    }
}
