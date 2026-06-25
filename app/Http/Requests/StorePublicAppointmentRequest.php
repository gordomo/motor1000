<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El acceso ya está gateado por el middleware de API key (brand.apikey).
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'  => ['nullable', 'integer'],
            'servicios'  => ['required', 'array', 'min:1'],
            'servicios.*'=> ['string', 'max:120'],
            'fecha'      => ['required', 'date_format:Y-m-d'],
            'hora'       => ['required', 'date_format:H:i'],
            'nombre'     => ['required', 'string', 'max:255'],
            'whatsapp'   => ['required', 'string', 'max:50'],
            'vehiculo'   => ['nullable', 'string', 'max:255'],
            'comentario' => ['nullable', 'string', 'max:1000'],
            // Honeypot anti-spam: debe llegar vacío.
            '_hp'        => ['nullable', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'servicios.required' => 'Elegí al menos un servicio.',
            'fecha.date_format'  => 'Fecha inválida.',
            'hora.date_format'   => 'Hora inválida.',
            'nombre.required'    => 'Ingresá tu nombre.',
            'whatsapp.required'  => 'Ingresá un WhatsApp de contacto.',
            '_hp.max'            => 'Solicitud rechazada.',
        ];
    }
}
