<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Extensions;

use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Models\Link;
use Illuminate\Support\Str;

final class AffiliateLinkGate implements LinkGateInterface
{
    public function blockedReason(Link $link): ?string
    {
        if ($link->subject_type !== (new AffiliateLink)->getMorphClass()) {
            return null;
        }

        if ($link->subject_id === null || ! Str::isUuid($link->subject_id)) {
            return 'link_unknown';
        }

        $affiliateLink = AffiliateLink::query()->withoutGlobalScope('affiliate_owner')
            ->with(['affiliate' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class)])
            ->find($link->subject_id);

        if ($affiliateLink === null || $affiliateLink->affiliate === null || ! $affiliateLink->trackedLink()->withoutGlobalScope(OwnerScope::class)->whereKey($link->getKey())->exists()) {
            return 'link_unknown';
        }

        return ! $affiliateLink->isEnabled() || ! $affiliateLink->affiliate->canBeAttributed()
            ? 'affiliate_link_inactive' : null;
    }
}
