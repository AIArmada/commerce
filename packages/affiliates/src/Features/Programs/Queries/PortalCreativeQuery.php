<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Features\Programs\Api\PortalCreativeReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class PortalCreativeQuery implements PortalCreativeReader
{
    public function assets(string $affiliateId): array
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);

        $generalCreatives = AffiliateProgramCreative::query()
            ->general()
            ->with(['media', 'program'])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->limit(200)
            ->get();

        $activeMemberships = AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', MembershipStatus::Approved->value)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->select('program_id');

        $programCreatives = AffiliateProgramCreative::query()
            ->whereIn('program_id', $activeMemberships)
            ->whereHas('program')
            ->with(['media', 'program'])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->limit(200)
            ->get();

        return array_values($this->mapAssets(collect($generalCreatives)->concat($programCreatives), $affiliate)->all());
    }

    /**
     * @param  Collection<int, AffiliateProgramCreative>  $creatives
     * @return Collection<int, array<string, mixed>>
     */
    private function mapAssets(Collection $creatives, Affiliate $affiliate): Collection
    {
        return $creatives
            ->map(fn (AffiliateProgramCreative $creative) => $this->mapAsset($creative, $affiliate))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAsset(AffiliateProgramCreative $creative, Affiliate $affiliate): array
    {
        $metadata = $creative->metadata ?? [];
        $media = $creative->getFirstMedia('creative_asset');
        $campaign = $creative->program?->name ?? $metadata['campaign'] ?? 'General';
        $defaultCategory = match ($creative->type) {
            'banner' => 'Banners',
            'image' => 'Images',
            'video' => 'Videos',
            'pdf', 'document' => 'Documents',
            default => 'Other',
        };

        return [
            'id' => $creative->getKey(),
            'title' => $creative->name,
            'description' => $creative->description ?? '',
            'type' => $creative->type,
            'program' => $creative->program?->name ?? 'General',
            'format' => $metadata['format'] ?? mb_strtoupper(pathinfo($media?->file_name ?? '', PATHINFO_EXTENSION) ?: $creative->type),
            'category' => $metadata['category'] ?? $defaultCategory,
            'campaign' => $campaign,
            'platforms' => $metadata['platforms'] ?? [],
            'dimensions' => $creative->getDimensions() ?? '—',
            'status' => $metadata['status'] ?? 'Approved',
            'status_color' => $metadata['status_color'] ?? 'success',
            'thumbnail' => $media !== null && str_starts_with($media->mime_type ?? '', 'image/') ? $media->getUrl() : null,
            'download_url' => $creative->getAssetUrl(),
            'affiliate_url' => $creative->getTrackingUrl($affiliate),
            'caption' => $metadata['caption'] ?? $creative->description ?? '',
            'downloads' => $metadata['downloads'] ?? 0,
        ];
    }
}
