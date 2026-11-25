<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;

final readonly class PortalProgramRecord
{
    /** @param list<array{id: string, name: string, type: string, download_url: ?string, destination_url: string}> $creatives */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ProgramStatus $status,
        public bool $requiresApproval,
        public ?MembershipStatus $membershipStatus,
        public bool $activeMembership,
        public bool $canJoin,
        public array $creatives,
    ) {}
}
