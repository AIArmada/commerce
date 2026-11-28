<?php

declare(strict_types=1);

namespace AIArmada\Membership\Data;

final readonly class MembershipIdentity
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}
}
