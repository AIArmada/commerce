<?php

declare(strict_types=1);

namespace AIArmada\Membership\Data;

final readonly class InvitationRecipientData
{
    public function __construct(
        public string $channel,
        public string $identifier,
    ) {}
}
