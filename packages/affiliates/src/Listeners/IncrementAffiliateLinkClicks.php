<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Events\LinkClicked;

final class IncrementAffiliateLinkClicks
{
    public function handle(LinkClicked $event): void
    {
        if ($event->click->is_bot || $event->link->subject_type !== (new AffiliateLink)->getMorphClass()) {
            return;
        }

        $link = AffiliateLink::query()->withoutGlobalScope('affiliate_owner')
            ->with(['affiliate' => fn ($query) => $query->withoutOwnerScope()])
            ->find($event->link->subject_id);

        if ($link !== null) {
            $owner = OwnerContext::fromTypeAndId($link->affiliate->owner_type, $link->affiliate->owner_id);
            OwnerContext::withOwner($owner, fn () => $link->incrementClicks());
        }
    }
}
