<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Actions;

use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkData;
use AIArmada\Affiliates\Models\AffiliateLink;
use Carbon\CarbonImmutable;

final readonly class ManageTrackingLinks implements TrackingLinkAdministration
{
    public function __construct(
        private CreateTrackingLink $creator,
        private UpdateTrackingLink $updater,
    ) {}

    public function create(string $affiliateId, string $destinationUrl, array $attributes = []): TrackingLinkData
    {
        return $this->data($this->creator->handle($affiliateId, $destinationUrl, $attributes));
    }

    public function update(string $linkId, array $attributes): TrackingLinkData
    {
        return $this->data($this->updater->handle($linkId, $attributes));
    }

    public function setEnabled(string $linkId, bool $enabled): void
    {
        AffiliateLink::query()->findOrFail($linkId)->update([
            'deactivated_at' => $enabled ? null : CarbonImmutable::now(),
        ]);
    }

    public function recordClick(string $linkId): void
    {
        AffiliateLink::query()->whereKey($linkId)->increment('clicks');
    }

    public function recordConversion(string $linkId): void
    {
        AffiliateLink::query()->whereKey($linkId)->increment('conversions');
    }

    private function data(AffiliateLink $link): TrackingLinkData
    {
        return new TrackingLinkData((string) $link->getKey(), $link->tracking_url, $link->subject_type, $link->subject_key);
    }
}
