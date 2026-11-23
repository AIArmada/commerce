<?php

declare(strict_types=1);

namespace AIArmada\Membership\Data;

final readonly class InvitationActorData
{
    public function __construct(
        public string $id,
        public ?string $email = null,
    ) {}
}
