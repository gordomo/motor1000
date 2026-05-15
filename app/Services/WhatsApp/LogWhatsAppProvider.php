<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

class LogWhatsAppProvider implements WhatsAppProviderInterface
{
    public function send(string $to, string $message, array $options = []): array
    {
        Log::channel('stack')->info('[WhatsApp LOG] Message', [
            'to'      => $to,
            'message' => $message,
        ]);

        return ['sid' => 'log_' . uniqid(), 'status' => 'sent'];
    }

    public function sendTemplate(string $to, string $template, array $variables = []): array
    {
        $body = $template;
        foreach ($variables as $key => $value) {
            $body = str_replace("{{$key}}", $value, $body);
        }

        return $this->send($to, $body);
    }

    public function getDeliveryStatus(string $messageId): string
    {
        return 'delivered';
    }
}
