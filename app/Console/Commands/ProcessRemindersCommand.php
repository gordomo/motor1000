<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\Tenant;
use App\Services\CommunicationService;
use Illuminate\Console\Command;

class ProcessRemindersCommand extends Command
{
    protected $signature   = 'reminders:process';
    protected $description = 'Process and send due reminders to customers';

    public function handle(CommunicationService $communicationService): void
    {
        $this->info('Processing reminders...');

        Reminder::with(['customer', 'vehicle', 'tenant'])
            ->where('status', 'pending')
            ->where('due_at', '<=', now()->addDays(1))
            ->chunk(100, function ($reminders) use ($communicationService) {
                foreach ($reminders as $reminder) {
                    try {
                        app()->instance('current.tenant', $reminder->tenant);

                        $customer = $reminder->customer;

                        if ($customer->whatsapp && $customer->whatsapp_opted_in) {
                            $communicationService->send(new \App\DTOs\SendCommunicationDTO(
                                tenantId:   $reminder->tenant_id,
                                customerId: $customer->id,
                                channel:    'whatsapp',
                                to:         $customer->whatsapp,
                                body:       "Olá {$customer->name}! Lembrete: {$reminder->title}. Agende já!",
                                template:   'maintenance_reminder',
                                reminderId: $reminder->id,
                            ));
                        }

                        $reminder->update(['status' => 'sent', 'sent_at' => now()]);

                        $this->line("  Sent reminder #{$reminder->id} to {$customer->name}");
                    } catch (\Throwable $e) {
                        $this->error("  Failed reminder #{$reminder->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info('Done.');
    }
}
