<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\ProgramLinkVolume;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\Support\RevenueVolume;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;

final readonly class ReadTrackingLinks implements TrackingLinkReader
{
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

        $connection = (new AffiliateConversion)->getConnection();
        if ($connection !== (new AffiliateLink)->getConnection()
            || $connection !== (new AffiliateAttribution)->getConnection()
            || $connection !== (new Affiliate)->getConnection()) {
            throw new LogicException('Program link volume requires a shared database connection.');
        }

        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        $links = AffiliateLink::query()->where('affiliate_id', $affiliateId)->where('program_id', $programId)->select('id');
        $attributions = AffiliateAttribution::query()->where('affiliate_id', $affiliateId)->whereIn('affiliate_link_id', clone $links)->select('id');
        $query = $affiliate->conversions()->where(function (Builder $query) use ($links, $attributions): void {
            $query->whereIn('affiliate_link_id', $links)->orWhereIn('affiliate_attribution_id', $attributions);
        });

        if ($since !== null) {
            $query->where('occurred_at', '>=', $since);
        }

        if ($approvedOnly) {
            $query->whereIn('status', [ApprovedConversion::value(), PaidConversion::value()]);
        }

        $rows = $query->toBase()
            ->selectRaw('commission_currency as currency, COUNT(*) as conversion_count, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as total')
            ->groupBy('commission_currency')->get();

        return new ProgramLinkVolume((int) $rows->sum('conversion_count'), RevenueVolume::foldRows($rows, $fallbackCurrency));
    }
}
