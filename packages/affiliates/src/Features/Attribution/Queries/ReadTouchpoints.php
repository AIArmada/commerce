<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Queries;

use AIArmada\Affiliates\Data\AttributionTouch;
use AIArmada\Affiliates\Features\Attribution\Api\TouchpointReader;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateTouchpoint;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

final readonly class ReadTouchpoints implements TouchpointReader
{
    public function distributionTouches(string $attributionId): array
    {
        return AffiliateTouchpoint::query()->forOwner()
            ->where('affiliate_attribution_id', $attributionId)
            ->orderBy('touched_at')->orderBy('id')
            ->get(['affiliate_id', 'touched_at'])
            ->map(static fn (AffiliateTouchpoint $touch): AttributionTouch => new AttributionTouch(
                $touch->affiliate_id,
                $touch->touched_at?->toImmutable(),
            ))->values()->all();
    }

    public function fingerprintCount(string $affiliateId, string $fingerprint, CarbonImmutable $since): int
    {
        return AffiliateTouchpoint::query()->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->where('fingerprint', $fingerprint)
            ->where('touched_at', '>=', $since)
            ->count();
    }

    public function latestOtherIp(string $affiliateId, string $ipAddress): ?array
    {
        $touch = AffiliateTouchpoint::query()->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->where('ip_address', '!=', $ipAddress)
            ->whereNotNull('touched_at')
            ->latest('touched_at')->orderBy('id')
            ->first(['ip_address', 'touched_at']);

        if ($touch === null || $touch->ip_address === null || $touch->touched_at === null) {
            return null;
        }

        return ['ip_address' => $touch->ip_address, 'touched_at' => $touch->touched_at->toImmutable()];
    }

    public function clickStats(string $affiliateId, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $row = AffiliateTouchpoint::query()->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->whereBetween('touched_at', [$start, $end])
            ->toBase()
            ->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT ip_address) as unique_clicks')
            ->first();

        return ['clicks' => (int) ($row->clicks ?? 0), 'unique_clicks' => (int) ($row->unique_clicks ?? 0)];
    }

    public function breakdown(string $affiliateId, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $rows = AffiliateTouchpoint::query()->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->whereBetween('touched_at', [$start, $end])
            ->toBase()->selectRaw('source, campaign, COUNT(*) as count')
            ->groupBy('source', 'campaign')->get();

        $bySource = [];
        $byCampaign = [];

        foreach ($rows as $row) {
            if (is_string($row->source)) {
                $bySource[$row->source] = ($bySource[$row->source] ?? 0) + (int) $row->count;
            }

            if (is_string($row->campaign)) {
                $byCampaign[$row->campaign] = ($byCampaign[$row->campaign] ?? 0) + (int) $row->count;
            }
        }

        return ['by_source' => $bySource, 'by_campaign' => $byCampaign];
    }

    public function subjectVisits(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return AffiliateTouchpoint::query()->forOwner()
            ->whereBetween('touched_at', [$start, $end])
            ->whereNotNull('subject_type')->whereNotNull('subject_key')
            ->toBase()
            ->selectRaw('subject_type, subject_key, MAX(subject_title_snapshot) as subject_title_snapshot, COUNT(*) as visits')
            ->groupBy('subject_type', 'subject_key')->get()
            ->map(static fn ($row): array => [
                'subject_type' => (string) $row->subject_type,
                'subject_key' => (string) $row->subject_key,
                'subject_title_snapshot' => $row->subject_title_snapshot === null ? null : (string) $row->subject_title_snapshot,
                'visits' => (int) $row->visits,
            ])->values()->all();
    }

    public function existsForAffiliate(string $touchpointId, string $affiliateId): bool
    {
        return Str::isUuid($touchpointId) && Str::isUuid($affiliateId)
            && AffiliateTouchpoint::query()->forOwner()->whereKey($touchpointId)->where('affiliate_id', $affiliateId)->exists();
    }
}
