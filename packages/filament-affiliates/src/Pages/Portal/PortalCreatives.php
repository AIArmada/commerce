<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class PortalCreatives extends PortalPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 3;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.creatives';

    public static function getNavigationLabel(): string
    {
        return __('Marketing Materials');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Marketing Materials');
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
                'generalCreatives' => [],
                'programCreatives' => [],
            ];
        }

        $generalCreatives = AffiliateProgramCreative::query()
            ->general()
            ->orderByDesc('created_at')
            ->get();

        $programCreatives = $this->getProgramCreatives($affiliate);

        return [
            'hasAffiliate' => true,
            'affiliate' => $affiliate,
            'generalCreatives' => $this->mapCreatives($generalCreatives, $affiliate),
            'programCreatives' => $programCreatives,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getProgramCreatives(Affiliate $affiliate): array
    {
        $programs = $affiliate->programs()
            ->withPivot('status')
            ->get()
            ->filter(fn (AffiliateProgram $program): bool => $program->getAttribute('pivot')->status === 'approved');

        return $programs
            ->map(fn (AffiliateProgram $program) => [
                'id' => $program->getKey(),
                'name' => $program->name,
                'creatives' => $this->mapCreatives(
                    $program->creatives()->orderByDesc('created_at')->get(),
                    $affiliate,
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AffiliateProgramCreative>  $creatives
     * @return array<int, array<string, mixed>>
     */
    private function mapCreatives(Collection $creatives, Affiliate $affiliate): array
    {
        return $creatives
            ->map(fn (AffiliateProgramCreative $creative) => [
                'id' => $creative->getKey(),
                'name' => $creative->name,
                'type' => $creative->type,
                'description' => $creative->description,
                'asset_url' => $creative->asset_url ?? $creative->getFirstMediaUrl('creative_asset'),
                'width' => $creative->width,
                'height' => $creative->height,
                'destination_url' => $creative->destination_url,
                'tracking_code' => $creative->tracking_code,
                'tracking_url' => $creative->getTrackingUrl($affiliate),
                'embed_code' => $creative->getEmbedCode($affiliate),
                'dimensions' => $creative->getDimensions(),
            ])
            ->values()
            ->all();
    }

    public function copyEmbedCode(string $creativeId): void
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return;
        }

        $creative = AffiliateProgramCreative::query()->find($creativeId);

        if (! $creative) {
            Notification::make()
                ->title(__('Creative not found'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Embed code copied to clipboard'))
            ->success()
            ->send();
    }
}
