<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReference;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Data\CommunicationContextData;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DispatchAffiliateNotification
{
    public function enabled(): bool
    {
        return $this->dispatcher() !== null;
    }

    public function send(AffiliateLifecycleReference $reference, Notification $notification, CommunicationContextData $context): void
    {
        $dispatcher = $this->dispatcher();

        if ($dispatcher === null || ! Str::isUuid($reference->id)) {
            return;
        }

        $affiliate = Affiliate::query()->withoutOwnerScope()->find($reference->id);

        if ($affiliate === null) {
            return;
        }

        if (! $reference->matches($affiliate->lifecycleReference())) {
            throw new InvalidArgumentException('Trusted affiliate owner or identity does not match the canonical source.');
        }

        $owner = OwnerContext::fromTypeAndIdOrFail($reference->ownerType, $reference->ownerId);

        OwnerContext::withOwner($owner, static fn () => $dispatcher->notify($affiliate, $notification, $context));
    }

    private function dispatcher(): ?CommunicationManager
    {
        if (! config('affiliates.notifications.enabled', true) || ! interface_exists(CommunicationManager::class)) {
            return null;
        }

        $bound = app()->bound(CommunicationManager::class);
        $loaded = isset(app()->getLoadedProviders()['AIArmada\Communications\CommunicationsServiceProvider']);

        return $bound || $loaded ? app(CommunicationManager::class) : null;
    }
}
