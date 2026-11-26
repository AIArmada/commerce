<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Identity\Queries;

use AIArmada\FilamentAffiliates\Features\Identity\Api\AffiliateAdminLinks;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource;

final class AffiliateResourceLinks implements AffiliateAdminLinks
{
    public function view(string $affiliateId): string
    {
        return AffiliateResource::getUrl('view', ['record' => $affiliateId]);
    }
}
