<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\EntryPoints\Listeners;

use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Links\Events\LinkClicked;
use Illuminate\Support\Str;

final readonly class IncrementAffiliateLinkClicks
{
    public function __construct(private TrackingLinkAdministration $links) {}

    public function handle(LinkClicked $event): void
    {
        if ($event->click->is_bot
            || $event->link->subject_type !== (new AffiliateLink)->getMorphClass()
            || $event->link->subject_id === null
            || ! Str::isUuid($event->link->subject_id)
            || (string) $event->click->link_id !== (string) $event->link->getKey()) {
            return;
        }

        $link = AffiliateLink::query()->withoutGlobalScope('affiliate_owner')
            ->with(['affiliate' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class)])
            ->find($event->link->subject_id);

        if ($link !== null && $link->affiliate !== null && $link->trackedLink()->withoutGlobalScope(OwnerScope::class)->whereKey($event->link->getKey())->exists()) {
            $owner = OwnerContext::fromTypeAndId($link->affiliate->owner_type, $link->affiliate->owner_id);
            OwnerContext::withOwner($owner, fn () => $this->links->recordClick((string) $link->getKey()));
        }
    }
}
