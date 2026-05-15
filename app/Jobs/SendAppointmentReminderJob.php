<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CommunicationService $service): void
    {
        Appointment::with(['customer', 'vehicle', 'tenant'])
            ->where('status', 'scheduled')
            ->where('reminder_sent', false)
            ->whereBetween('scheduled_at', [now()->addHours(23), now()->addHours(25)])
            ->chunk(50, function ($appointments) use ($service) {
                foreach ($appointments as $appointment) {
                    app()->instance('current.tenant', $appointment->tenant);
                    $service->notifyAppointmentReminder($appointment);
                    $appointment->update(['reminder_sent' => true]);
                }
            });
    }
}
