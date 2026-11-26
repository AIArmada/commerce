<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\EntryPoints\Listeners;

use AIArmada\Affiliates\Data\AffiliateEventReference;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionSignalReader;
use AIArmada\Affiliates\Features\Identity\Actions\DispatchAffiliateNotification;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReference;
use AIArmada\Affiliates\Features\Identity\Notifications\ConversionRecordedNotification;
use AIArmada\Communications\Data\CommunicationContextData;

final readonly class NotifyConversionRecorded
{
    public function __construct(
        private ConversionSignalReader $conversions,
        private DispatchAffiliateNotification $notifications,
    ) {}

    public function handle(AffiliateConversionRecorded $event): void
    {
        if (! $this->notifications->enabled()) {
            return;
        }

        $conversion = $event->conversion;
        $record = $this->conversions->find(new AffiliateEventReference(
            id: $conversion->id,
            affiliateId: $conversion->affiliateId,
            affiliateCode: $conversion->affiliateCode,
            ownerType: $conversion->ownerType,
            ownerId: $conversion->ownerId === null ? null : (string) $conversion->ownerId,
        ));

        if ($record === null) {
            return;
        }

        $reference = $record->reference;

        $this->notifications->send(
            new AffiliateLifecycleReference($reference->affiliateId, $reference->ownerType, $reference->ownerId),
            new ConversionRecordedNotification($record->commissionCurrency, $record->commissionMinor),
            new CommunicationContextData(
                purpose: 'affiliates.conversion_recorded',
                subjectType: 'affiliate_conversion',
                subjectId: $reference->id,
                idempotencyKey: 'conversion:' . $reference->id,
            ),
        );
    }
}
