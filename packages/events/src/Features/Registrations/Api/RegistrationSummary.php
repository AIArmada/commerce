<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Api;

final readonly class RegistrationSummary
{
    public function __construct(
        public string $id,
        public string $registrationNo,
        public string $eventId,
        public ?string $occurrenceId,
        public ?string $sessionId,
        public string $status,
        public ?string $registrantType,
        public ?string $registrantId,
        public int $totalAmount,
        public string $currency,
        public ?string $paymentStatus,
        public bool $isBundleRoot,
        public ?string $parentRegistrationId,
        public ?string $externalOrderId,
        public ?string $externalOrderType,
    ) {}
}
