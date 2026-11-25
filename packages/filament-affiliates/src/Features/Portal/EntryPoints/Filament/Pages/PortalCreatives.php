<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages;

use AIArmada\Affiliates\Features\Programs\Api\PortalCreativeReader;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Concerns\PortalPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

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

        $assets = collect(app(PortalCreativeReader::class)->assets((string) $affiliate->getKey()));

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
}
