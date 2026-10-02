<?php

namespace App\Services;

use App\Scopes\TenantScope;
use App\DTOs\SendCommunicationDTO;
use App\Jobs\SendCommunicationJob;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use App\Models\WorkOrder;

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

        $template = $this->resolveTemplate($order->tenant_id, 'vehicle_ready', 'whatsapp');
        $body = $template
            ? $template->render([
                'customer_name'   => $customer->name,
                'vehicle'         => $order->vehicle->display_name,
                'work_order'      => $order->number,
                'workshop_name'   => $order->tenant->name,
            ])
            : "Hola {$customer->name}, tu vehículo {$order->vehicle->display_name} ya está listo para retirar. Orden: {$order->number}";

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
            $emailTemplate = $this->resolveTemplate($order->tenant_id, 'vehicle_ready', 'email');
            $emailBody = $emailTemplate
                ? $emailTemplate->render([
                    'customer_name' => $customer->name,
                    'vehicle'       => $order->vehicle->display_name,
                    'work_order'    => $order->number,
                ])
                : $body;

            $this->send(new SendCommunicationDTO(
                tenantId:    $order->tenant_id,
                customerId:  $customer->id,
                channel:     'email',
                to:          $customer->email,
                subject:     "Tu vehículo está listo - Orden {$order->number}",
                body:        $emailBody,
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

        $body = "Hola {$customer->name}, te recordamos tu turno de mañana a las {$appointment->scheduled_at->format('H:i')}. ¡Te esperamos!";

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

    private function resolveTemplate(int $tenantId, string $event, string $channel): ?CommunicationTemplate
    {
        return CommunicationTemplate::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->where('event', $event)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();
    }
}
