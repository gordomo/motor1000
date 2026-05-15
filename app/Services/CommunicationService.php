<?php

namespace App\Services;

use App\DTOs\SendCommunicationDTO;
use App\Jobs\SendCommunicationJob;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use App\Models\WorkOrder;

class CommunicationService
{
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
            : "Olá {$customer->name}, seu veículo {$order->vehicle->display_name} está pronto para retirada! OS: {$order->number}";

        if ($customer->whatsapp && $customer->whatsapp_opted_in) {
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
                subject:     "Seu veículo está pronto - OS {$order->number}",
                body:        $emailBody,
                template:    'vehicle_ready',
                workOrderId: $order->id,
            ));
        }
    }

    public function notifyAppointmentReminder(\App\Models\Appointment $appointment): void
    {
        $customer = $appointment->customer;

        if (! $customer) {
            return;
        }

        $body = "Olá {$customer->name}, lembrando do seu agendamento amanhã às {$appointment->scheduled_at->format('H:i')}. Te esperamos!";

        if ($customer->whatsapp && $customer->whatsapp_opted_in) {
            $this->send(new SendCommunicationDTO(
                tenantId:   $appointment->tenant_id,
                customerId: $customer->id,
                channel:    'whatsapp',
                to:         $customer->whatsapp,
                body:       $body,
                template:   'appointment_reminder',
            ));
        }
    }

    private function resolveTemplate(int $tenantId, string $event, string $channel): ?CommunicationTemplate
    {
        return CommunicationTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event', $event)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();
    }
}
