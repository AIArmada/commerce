<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

use DateTimeImmutable;

final readonly class WebhookOrder
{
    public function __construct(
        public string $id,
        public string $orderReference,
        public ?string $trackingNumber,
        public ?string $ownerType,
        public ?string $ownerId,
        public ?DateTimeImmutable $problemAt,
        public ?DateTimeImmutable $exceptionAt,
        public ?DateTimeImmutable $returnedAt,
        public ?DateTimeImmutable $deliveredAt,
    ) {}
}
