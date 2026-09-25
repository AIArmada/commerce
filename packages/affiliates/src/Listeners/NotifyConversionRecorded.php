<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Notifications\ConversionRecordedNotification;
use AIArmada\Communications\Actions\DispatchManagedNotificationAction;
use AIArmada\Communications\Data\CommunicationContextData;

/**
 * Notifies the affiliate on each recorded conversion.
 */
final class NotifyConversionRecorded
{
    public function handle(AffiliateConversionRecorded $event): void
    {
        $dispatcher = self::dispatcher();

        if ($dispatcher === null) {
            return;
        }

        $affiliate = Affiliate::query()->whereKey($event->conversion->affiliateId)->first();

        if (! $affiliate instanceof Affiliate) {
            return;
        }

        $dispatcher->handle(
            $affiliate,
            new ConversionRecordedNotification($event->conversion),
            new CommunicationContextData(
                purpose: 'affiliates.conversion_recorded',
                subjectType: 'affiliate_conversion',
                subjectId: $event->conversion->id,
                idempotencyKey: 'conversion:' . $event->conversion->id,
            ),
        );
    }

    private static function dispatcher(): ?DispatchManagedNotificationAction
    {
        if (! config('affiliates.notifications.enabled', true)) {
            return null;
        }

        if (! class_exists(DispatchManagedNotificationAction::class)) {
            return null;
        }

        $bound = app()->bound(DispatchManagedNotificationAction::class);
        $loaded = isset(app()->getLoadedProviders()['AIArmada\Communications\CommunicationsServiceProvider']);

        if (! $bound && ! $loaded) {
            return null;
        }

        return app(DispatchManagedNotificationAction::class);
    }
}
