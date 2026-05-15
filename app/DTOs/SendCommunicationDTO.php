<?php

namespace App\DTOs;

readonly class SendCommunicationDTO
{
    public function __construct(
        public int $tenantId,
        public int $customerId,
        public string $channel,
        public string $to,
        public string $body,
        public ?string $subject = null,
        public ?string $template = null,
        public ?int $workOrderId = null,
        public ?int $reminderId = null,
        public array $metadata = [],
    ) {}
}
