<?php

namespace App\Services\WhatsApp;

interface WhatsAppProviderInterface
{
    public function send(string $to, string $message, array $options = []): array;

    public function sendTemplate(string $to, string $template, array $variables = []): array;

    public function getDeliveryStatus(string $messageId): string;
}
