<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Support\OwnerContext;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    private ?Affiliate $resolvedAffiliate = null;

    private bool $affiliateResolved = false;

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
        // Public marketplace: intentionally shows categories from all tenants — explicit global context.
        return OwnerContext::withOwner(null, function (): Collection {
            return AffiliateOfferCategory::query()
                ->withoutOwnerScope()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * @return Collection<int, AffiliateOffer>
     */
    public function getOffers(): Collection
    {
        // Public marketplace: intentionally shows offers from all tenants — explicit global context.
        return OwnerContext::withOwner(null, function (): Collection {
            $search = $this->search;

            return AffiliateOffer::withoutGlobalScope('owner_via_site')
                ->where('status', AffiliateOffer::STATUS_ACTIVE)
                ->where('is_public', true)
                ->when(mb_strlen((string) $search) >= 3, fn (Builder $query) => $query->where(function (Builder $q) use ($search): void {
                    $escaped = str_replace(['%', '_'], ['\%', '\_'], (string) $search);
                    $q->where('name', 'like', "%{$escaped}%")
                        ->orWhere('description', 'like', "%{$escaped}%");
                }))
                ->when($this->categoryFilter, fn (Builder $query) => $query->where('category_id', $this->categoryFilter))
                ->when($this->sortBy === 'featured', fn (Builder $query) => $query->orderByDesc('is_featured')->orderByDesc('created_at'))
                ->when($this->sortBy === 'newest', fn (Builder $query) => $query->orderByDesc('created_at'))
                ->when($this->sortBy === 'commission', fn (Builder $query) => $query->orderByDesc('commission_rate'))
                ->with([
                    'site' => fn ($query) => $query->withoutOwnerScope(),
                    'category' => fn ($query) => $query->withoutOwnerScope(),
                ])
                ->limit(50)
                ->get();
        });
    }

    public function getAffiliate(): ?Affiliate
    {
        if ($this->affiliateResolved) {
            return $this->resolvedAffiliate;
        }

        $this->affiliateResolved = true;

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

        // Public marketplace: find the user's affiliate regardless of owner — explicit global scope bypass.
        $this->resolvedAffiliate = OwnerContext::withOwner(null, fn (): ?Affiliate => Affiliate::query()
            ->withoutOwnerScope()
            ->where('contact_email', $email)
            ->whereState('status', Active::class)
            ->first());

        return $this->resolvedAffiliate;
    }

    public function hasApplied(AffiliateOffer $offer): bool
    {
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            return false;
        }

        return $this->withAffiliateOwnerContext($affiliate, fn (): bool => AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->exists());
    }

    public function getApplicationStatus(AffiliateOffer $offer): ?string
    {
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            return null;
        }

        return $this->withAffiliateOwnerContext($affiliate, fn (): ?string => AffiliateOfferApplication::query()
            ->where('offer_id', $offer->id)
            ->where('affiliate_id', $affiliate->id)
            ->value('status'));
    }

    public function applyForOffer(string $offerId, string $reason = ''): void
    {
        $offer = app(OfferManagementService::class)->resolvePublicOfferOrFail($offerId);

        if (! $offer->requires_approval) {
            $this->generateLink($offerId);

            return;
        }

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

        $this->withAffiliateOwnerContext($affiliate, fn (): AffiliateOfferApplication => app(OfferManagementService::class)
            ->applyForOffer($offer, $affiliate, $reason));

        Notification::make()
            ->title('Application submitted successfully')
            ->success()
            ->send();
    }

    public function generateLink(string $offerId): void
    {
        $offer = app(OfferManagementService::class)->resolvePublicOfferOrFail($offerId);
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            Notification::make()
                ->title('You must be an affiliate to generate links')
                ->danger()
                ->send();

            return;
        }

        $isApprovedForOffer = $this->withAffiliateOwnerContext($affiliate, fn (): bool => app(OfferManagementService::class)
            ->isApprovedForOffer($offer, $affiliate));

        if ($offer->requires_approval && ! $isApprovedForOffer) {
            Notification::make()
                ->title('Approval required')
                ->body('You must be approved for this offer before generating links.')
                ->danger()
                ->send();

            return;
        }

        $linkService = app(OfferLinkService::class);
        $link = $this->withAffiliateOwnerContext($affiliate, fn (): AffiliateOfferLink => $linkService->createLink($offer, $affiliate));
        $trackingUrl = $linkService->generateTrackingUrl($link);

        Notification::make()
            ->title('Link Generated')
            ->body($trackingUrl)
            ->success()
            ->persistent()
            ->send();
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function withAffiliateOwnerContext(Affiliate $affiliate, callable $callback): mixed
    {
        /** @var string|null $ownerType */
        $ownerType = $affiliate->owner_type;
        /** @var string|null $ownerId */
        $ownerId = $affiliate->owner_id;

        /** @var Model|null $owner */
        $owner = OwnerContext::fromTypeAndId($ownerType, $ownerId);

        return OwnerContext::withOwner($owner, $callback);
    }
}
