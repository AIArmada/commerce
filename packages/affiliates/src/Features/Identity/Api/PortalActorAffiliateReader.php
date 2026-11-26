<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface PortalActorAffiliateReader
{
    public function affiliateIdForActor(string $actorType, string $actorId): ?string;
}
