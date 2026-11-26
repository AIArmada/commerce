<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\ProgramLinkVolume;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkReader;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\Affiliates\Features\Conversions\Api\LinkConversionReader;
use DateTimeImmutable;
use Illuminate\Support\Str;
use LogicException;

final readonly class ReadTrackingLinks implements TrackingLinkReader
{
    public function __construct(private LinkConversionReader $conversions) {}

    public function hasRecords(string $affiliateId): bool
    {
        return Str::isUuid($affiliateId) && AffiliateLink::query()->where('affiliate_id', $affiliateId)->exists();
    }

    public function belongsTo(string $linkId, string $affiliateId): bool
    {
        return Str::isUuid($linkId) && Str::isUuid($affiliateId)
            && AffiliateLink::query()->where('affiliate_id', $affiliateId)->whereKey($linkId)->exists();
    }

    public function latestForDestination(string $affiliateId, string $destination): ?string
    {
        if (! Str::isUuid($affiliateId)) {
            return null;
        }

        $value = AffiliateLink::query()->where('affiliate_id', $affiliateId)
            ->where('destination_url', $destination)->whereNull('deactivated_at')
            ->latest()->orderByDesc('id')->value('tracking_url');

        return is_string($value) ? $value : null;
    }

    public function programVolume(string $affiliateId, string $programId, string $fallbackCurrency, ?DateTimeImmutable $since = null, bool $approvedOnly = false): ProgramLinkVolume
    {
        if (! Str::isUuid($affiliateId) || ! Str::isUuid($programId)) {
            return new ProgramLinkVolume(0, []);
        }

        $connection = (new AffiliateLink)->getConnection();

        if ($connection !== (new AffiliateAttribution)->getConnection()) {
            throw new LogicException('Program link volume requires a shared database connection.');
        }

        return $this->conversions->programVolume($affiliateId, $programId, $fallbackCurrency, spl_object_id($connection), $since, $approvedOnly);
    }
}
