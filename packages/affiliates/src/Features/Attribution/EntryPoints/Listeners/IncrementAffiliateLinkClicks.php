<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\EntryPoints\Listeners;

use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReader;
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
            ->find($event->link->subject_id);

        if ($link === null || ! $link->trackedLink()->withoutGlobalScope(OwnerScope::class)->whereKey($event->link->getKey())->exists()) {
            return;
        }

        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($link->getConnection()));
        $affiliate = app(AffiliateLifecycleReader::class)->findById($link->affiliate_id);

        if ($affiliate !== null && $affiliate->canBeAttributed) {
            $owner = OwnerContext::fromTypeAndIdOrFail($affiliate->reference->ownerType, $affiliate->reference->ownerId);
            OwnerContext::withOwner($owner, fn () => $this->links->recordClick((string) $link->getKey()));
        }
    }
}
