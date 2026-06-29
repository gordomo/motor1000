<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AppointmentConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
    }

    public function build(): self
    {
        $appt = $this->appointment->loadMissing(['tenant', 'customer']);

        // Link firmado y con vencimiento (3 días) para el doble opt-in.
        $confirmUrl = URL::temporarySignedRoute(
            'public.appointments.confirm',
            now()->addDays(3),
            ['appointment' => $appt->id]
        );

        return $this->subject('Confirmá tu turno en ' . ($appt->tenant->name ?? 'el taller'))
            ->view('emails.appointment-confirmation', [
                'appt'       => $appt,
                'confirmUrl' => $confirmUrl,
            ]);
    }
}
