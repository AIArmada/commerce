<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Extensions;

use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReader;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Data\LinkGateContext;
use Illuminate\Support\Str;

final class AffiliateLinkGate implements LinkGateInterface
{
    public function blockedReason(LinkGateContext $link): ?string
    {
        if ($link->subjectType !== (new AffiliateLink)->getMorphClass()) {
            return null;
        }

        if ($link->subjectId === null || ! Str::isUuid($link->subjectId)) {
            return 'link_unknown';
        }

        $affiliateLink = AffiliateLink::query()->withoutGlobalScope('affiliate_owner')
            ->find($link->subjectId);

        if ($affiliateLink === null || ! $affiliateLink->trackedLink()->withoutGlobalScope(OwnerScope::class)->whereKey($link->id)->exists()) {
            return 'link_unknown';
        }

        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($affiliateLink->getConnection()));
        $affiliate = app(AffiliateLifecycleReader::class)->findById($affiliateLink->affiliate_id);

        if ($affiliate === null) {
            return 'link_unknown';
        }

        return ! $affiliateLink->isEnabled() || ! $affiliate->canBeAttributed
            ? 'affiliate_link_inactive' : null;
    }
}
