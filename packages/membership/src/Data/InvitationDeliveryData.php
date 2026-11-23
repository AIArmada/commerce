<?php

declare(strict_types=1);

namespace AIArmada\Membership\Data;

use Carbon\CarbonImmutable;

final readonly class InvitationDeliveryData
{
    public function __construct(
        public string $invitationId,
        public string $channel,
        public string $recipient,
        public string $subjectType,
        public string $subjectId,
        public string $role,
        public string $token,
        public ?CarbonImmutable $expiresAt,
    ) {}
}
