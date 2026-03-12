<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Affiliates\Models\Affiliate;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

final class AffiliateMarketplacePage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Marketplace';

    protected static ?string $title = 'Offer Marketplace';

    protected static ?string $slug = 'affiliate-network/marketplace';

    protected string $view = 'filament-affiliate-network::pages.affiliate-marketplace';

    public ?string $search = '';

    public ?string $categoryFilter = null;

    public ?string $sortBy = 'featured';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group', 'Affiliate Network');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 10;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Offer Marketplace';
    }

    /**
     * @return Collection<int, AffiliateOfferCategory>
     */
    public function getCategories(): Collection
    {
        return AffiliateOfferCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, AffiliateOffer>
     */
    public function getOffers(): Collection
    {
        return AffiliateOffer::query()
            ->where('status', AffiliateOffer::STATUS_ACTIVE)
            ->where('is_public', true)
            ->when($this->search, fn (Builder $query) => $query->where(function (Builder $q): void {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryFilter, fn (Builder $query) => $query->where('category_id', $this->categoryFilter))
            ->when($this->sortBy === 'featured', fn (Builder $query) => $query->orderByDesc('is_featured')->orderByDesc('created_at'))
            ->when($this->sortBy === 'newest', fn (Builder $query) => $query->orderByDesc('created_at'))
            ->when($this->sortBy === 'commission', fn (Builder $query) => $query->orderByDesc('commission_rate'))
            ->with(['site', 'category'])
            ->limit(50)
            ->get();
    }

    public function getAffiliate(): ?Affiliate
    {
        /** @var Authenticatable|null $user */
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        /** @var string|null $email */
        $email = method_exists($user, 'getEmail')
            ? $user->getEmail()
            : ($user->email ?? null);

        if ($email === null) {
            return null;
        }

        return Affiliate::query()
            ->where('contact_email', $email)
            ->first();
    }

    public function hasApplied(AffiliateOffer $offer): bool
    {
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            return false;
        }

        return AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->exists();
    }

    public function getApplicationStatus(AffiliateOffer $offer): ?string
    {
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            return null;
        }

        return AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->value('status');
    }

    public function applyForOffer(string $offerId, string $reason = ''): void
    {
        $offer = AffiliateOffer::findOrFail($offerId);
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            Notification::make()
                ->title('You must be an affiliate to apply')
                ->danger()
                ->send();

            return;
        }

        if ($this->hasApplied($offer)) {
            Notification::make()
                ->title('You have already applied to this offer')
                ->warning()
                ->send();

            return;
        }

        app(OfferManagementService::class)->applyForOffer($offer, $affiliate, $reason);

        Notification::make()
            ->title('Application submitted successfully')
            ->success()
            ->send();
    }

    public function generateLink(string $offerId): void
    {
        $offer = AffiliateOffer::findOrFail($offerId);
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            Notification::make()
                ->title('You must be an affiliate to generate links')
                ->danger()
                ->send();

            return;
        }

        $linkService = app(OfferLinkService::class);
        $link = $linkService->createLink($offer, $affiliate);
        $trackingUrl = $linkService->generateTrackingUrl($link);

        Notification::make()
            ->title('Link Generated')
            ->body($trackingUrl)
            ->success()
            ->persistent()
            ->send();
    }
}
