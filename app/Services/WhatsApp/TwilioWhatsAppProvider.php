<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioWhatsAppProvider implements WhatsAppProviderInterface
{
    private string $sid;
    private string $token;
    private string $from;

    public function __construct()
    {
        $this->sid   = config('services.twilio.sid');
        $this->token = config('services.twilio.token');
        $this->from  = config('services.twilio.whatsapp_from');
    }

    public function send(string $to, string $message, array $options = []): array
    {
        try {
            $response = Http::withBasicAuth($this->sid, $this->token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                    'From' => "whatsapp:{$this->from}",
                    'To'   => "whatsapp:{$to}",
                    'Body' => $message,
                ]);

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed', ['error' => $e->getMessage(), 'to' => $to]);
            throw $e;
        }
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
        $response = Http::withBasicAuth($this->sid, $this->token)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages/{$messageId}.json");

        return $response->json('status', 'unknown');
    }
}
