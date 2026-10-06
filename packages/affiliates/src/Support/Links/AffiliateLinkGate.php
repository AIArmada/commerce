<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Links;

use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Models\Link;

final class AffiliateLinkGate implements LinkGateInterface
{
    public function blockedReason(Link $link): ?string
    {
        if ($link->subject_type !== (new AffiliateLink)->getMorphClass()) {
            return null;
        }

        $affiliateLink = AffiliateLink::query()->withoutGlobalScope('affiliate_owner')
            ->with(['affiliate' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class)])
            ->find($link->subject_id);

        if ($affiliateLink === null) {
            return 'link_unknown';
        }

        return ! $affiliateLink->isEnabled() || ! $affiliateLink->affiliate->canBeAttributed()
            ? 'affiliate_link_inactive' : null;
    }
}
