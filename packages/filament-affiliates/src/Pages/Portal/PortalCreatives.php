<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class PortalCreatives extends PortalPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPhoto;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.creatives';

    public static function getNavigationLabel(): string
    {
        return __('Creatives');
    }

    public static function getNavigationParent(): ?string
    {
        return PortalLinks::class;
    }

    public function getTitle(): string | Htmlable
    {
        return __('Creatives');
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return [
                'hasAffiliate' => false,
                'assets' => [],
                'campaigns' => [],
                'categories' => [],
                'formats' => [],
                'platforms' => [],
                'programs' => [],
                'affiliateCode' => null,
            ];
        }

        $generalCreatives = AffiliateProgramCreative::query()
            ->general()
            ->orderByDesc('created_at')
            ->get();

        $programCreatives = $this->getProgramCreatives($affiliate);

        $allCreatives = collect($generalCreatives)
            ->concat($programCreatives);

        $assets = $this->mapAssets($allCreatives, $affiliate);

        $campaigns = $assets->pluck('campaign')->filter()->unique()->values();
        $categories = $assets->pluck('category')->filter()->unique()->values();
        $formats = $assets->pluck('format')->filter()->unique()->values();
        $platforms = $assets->pluck('platforms')->flatten()->filter()->unique()->values();
        $programs = $assets->pluck('program')->filter()->unique()->values();

        return [
            'hasAffiliate' => true,
            'assets' => $assets,
            'campaigns' => $campaigns,
            'categories' => $categories,
            'formats' => $formats,
            'platforms' => $platforms,
            'programs' => $programs,
            'affiliateCode' => $affiliate->code,
        ];
    }

    /**
     * @return Collection<int, AffiliateProgramCreative>
     */
    private function getProgramCreatives(Affiliate $affiliate): Collection
    {
        $programs = $affiliate->programs()
            ->withPivot('status')
            ->get()
            ->filter(fn (AffiliateProgram $program): bool => $program->getAttribute('pivot')->status === 'approved');

        return $programs
            ->flatMap(
                fn (AffiliateProgram $program) => $program
                    ->creatives()
                    ->orderByDesc('created_at')
                    ->get()
                    ->each(fn (AffiliateProgramCreative $creative) => $creative->setRelation('program', $program))
            );
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
            'format' => $metadata['format'] ?? mb_strtoupper(pathinfo((string) $creative->asset_url, PATHINFO_EXTENSION) ?: $creative->type),
            'category' => $metadata['category'] ?? $defaultCategory,
            'campaign' => $campaign,
            'platforms' => $metadata['platforms'] ?? [],
            'dimensions' => $creative->getDimensions() ?? '—',
            'status' => $metadata['status'] ?? 'Approved',
            'status_color' => $metadata['status_color'] ?? 'success',
            'thumbnail' => $creative->getFirstMediaUrl('creative_asset', 'thumb') ?: $creative->asset_url,
            'download_url' => $creative->asset_url,
            'affiliate_url' => $creative->getTrackingUrl($affiliate),
            'caption' => $metadata['caption'] ?? $creative->description ?? '',
            'downloads' => $metadata['downloads'] ?? 0,
        ];
    }
}
