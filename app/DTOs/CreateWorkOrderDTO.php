<?php

namespace App\DTOs;

readonly class CreateWorkOrderDTO
{
    public function __construct(
        public int $tenantId,
        public int $customerId,
        public int $vehicleId,
        public ?int $mechanicId,
        public string $complaint,
        public string $priority = 'normal',
        public ?string $estimatedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tenantId:    $data['tenant_id'],
            customerId:  $data['customer_id'],
            vehicleId:   $data['vehicle_id'],
            mechanicId:  $data['mechanic_id'] ?? null,
            complaint:   $data['complaint'],
            priority:    $data['priority'] ?? 'normal',
            estimatedAt: $data['estimated_at'] ?? null,
        );
    }
}
