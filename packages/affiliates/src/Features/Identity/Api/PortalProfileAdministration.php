<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface PortalProfileAdministration
{
    public function save(string $affiliateId, string $actorType, string $actorId, ?string $actorEmail, PortalProfileInput $input): void;
}
