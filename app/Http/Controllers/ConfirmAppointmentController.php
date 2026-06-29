<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\View\View;

class ConfirmAppointmentController extends Controller
{
    /**
     * Confirmación del turno por el cliente (doble opt-in desde el email).
     * La firma del link ya fue validada por el middleware 'signed'.
     */
    public function __invoke(Appointment $appointment): View
    {
        // No re-confirmamos ni pisamos un estado más avanzado (en progreso, etc.).
        if (! $appointment->client_confirmed_at && in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
            $appointment->update([
                'status'              => 'confirmed',
                'client_confirmed_at' => now(),
            ]);
        }

        return view('public.appointment-confirmed', [
            'appt' => $appointment->loadMissing('tenant'),
        ]);
    }
}
