<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

final readonly class PortalProgramRecord
{
    /** @param list<array{id: string, name: string, type: string, download_url: ?string, destination_url: string}> $creatives */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public string $status,
        public string $statusLabel,
        public bool $requiresApproval,
        public ?string $membershipStatus,
        public ?string $membershipStatusLabel,
        public bool $activeMembership,
        public bool $canJoin,
        public array $creatives,
    ) {}

    public function isPending(): bool
    {
        return $this->membershipStatus === 'pending';
    }
}
