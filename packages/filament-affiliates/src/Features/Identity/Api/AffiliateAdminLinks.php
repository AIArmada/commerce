<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Identity\Api;

interface AffiliateAdminLinks
{
    public function view(string $affiliateId): string;
}
