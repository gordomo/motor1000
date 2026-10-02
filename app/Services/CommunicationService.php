<?php

namespace App\Services;

use App\DTOs\SendCommunicationDTO;
use App\Jobs\SendCommunicationJob;
use App\Models\Communication;
use App\Models\WorkOrder;
use App\Support\Mensajes;

class CommunicationService
{
    /**
     * Con el proveedor 'log' (el de prod hoy) los WhatsApp no salen: solo se
     * escriben en el log. Antes se registraban como enviados igual, y el
     * taller creía que el cliente recibía los avisos. Ver docs/WHATSAPP.md.
     */
    public static function whatsappConectado(): bool
    {
        return config('services.whatsapp.provider', 'log') !== 'log';
    }

    public function send(SendCommunicationDTO $dto): Communication
    {
        $communication = Communication::create([
            'tenant_id'     => $dto->tenantId,
            'customer_id'   => $dto->customerId,
            'work_order_id' => $dto->workOrderId,
            'reminder_id'   => $dto->reminderId,
            'channel'       => $dto->channel,
            'direction'     => 'outbound',
            'to'            => $dto->to,
            'subject'       => $dto->subject,
            'body'          => $dto->body,
            'template'      => $dto->template,
            'metadata'      => $dto->metadata,
            'status'        => 'pending',
        ]);

        SendCommunicationJob::dispatch($communication);

        return $communication;
    }

    public function notifyVehicleReady(WorkOrder $order): void
    {
        $customer = $order->customer;

        if (! $customer) {
            return;
        }

        // Textos editables en Plantillas de comunicación (evento "vehicle_ready").
        // customer_name, vehicle, work_order y workshop_name son los nombres que
        // usaban las plantillas viejas: se siguen completando.
        $variables = [
            'nombre'        => strtok(trim((string) $customer->name), ' ') ?: $customer->name,
            'taller'        => $order->tenant?->name,
            'vehiculo'      => $order->vehicle?->display_name,
            'orden'         => $order->number,
            'customer_name' => $customer->name,
            'vehicle'       => $order->vehicle?->display_name,
            'work_order'    => $order->number,
            'workshop_name' => $order->tenant?->name,
        ];
        $body = Mensajes::cuerpo('vehicle_ready', 'whatsapp', $variables);

        if (self::whatsappConectado() && $customer->whatsapp && $customer->whatsapp_opted_in) {
            $this->send(new SendCommunicationDTO(
                tenantId:    $order->tenant_id,
                customerId:  $customer->id,
                channel:     'whatsapp',
                to:          $customer->whatsapp,
                body:        $body,
                template:    'vehicle_ready',
                workOrderId: $order->id,
            ));
        }

        if ($customer->email && $customer->email_opted_in) {
            $this->send(new SendCommunicationDTO(
                tenantId:    $order->tenant_id,
                customerId:  $customer->id,
                channel:     'email',
                to:          $customer->email,
                subject:     Mensajes::asunto('vehicle_ready', 'email', $variables),
                body:        Mensajes::cuerpo('vehicle_ready', 'email', $variables),
                template:    'vehicle_ready',
                workOrderId: $order->id,
            ));
        }
    }

    /** @return bool si se mandó el aviso (false si WhatsApp no está conectado o el cliente no tiene). */
    public function notifyAppointmentReminder(\App\Models\Appointment $appointment): bool
    {
        $customer = $appointment->customer;

        if (! $customer || ! self::whatsappConectado() || ! $customer->whatsapp || ! $customer->whatsapp_opted_in) {
            return false;
        }

        // Mismo texto que "Para hoy" (evento "turno_manana", editable en Plantillas).
        $body = \App\Support\ParaHoy::mensajeTurno($appointment);

        $this->send(new SendCommunicationDTO(
            tenantId:   $appointment->tenant_id,
            customerId: $customer->id,
            channel:    'whatsapp',
            to:         $customer->whatsapp,
            body:       $body,
            template:   'appointment_reminder',
        ));

        return true;
    }
}
