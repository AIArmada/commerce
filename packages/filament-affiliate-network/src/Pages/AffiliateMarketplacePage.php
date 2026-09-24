<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Pages;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\CommerceSupport\Support\OwnerContext;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use UnitEnum;

/**
 * Public offer marketplace.
 *
 * Intentionally visible to every authenticated panel user without the network
 * admin gate: listed offers are published + public only, and every mutation
 * (apply, link generation) resolves the caller's own affiliate and runs
 * inside that affiliate's owner context. State-changing actions are
 * rate-limited per user (see hitMarketplaceThrottle()).
 */
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

    private ?NetworkAffiliate $resolvedAffiliate = null;

    private bool $affiliateResolved = false;

    /** @var Collection<int, AffiliateOffer>|null */
    private ?Collection $memoizedOffers = null;

    /** @var array<string, ?string>|null offer id => status value (null when never applied) */
    private ?array $applicationStatusMap = null;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group');
    }

    public static function getNavigationSort(): int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 10;
    }

    public function getTitle(): string
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
        if ($this->memoizedOffers !== null) {
            return $this->memoizedOffers;
        }

        // Public marketplace: intentionally shows offers from all tenants — explicit global context.
        return $this->memoizedOffers = OwnerContext::withOwner(null, function (): Collection {
            $search = $this->search;

            return AffiliateOffer::withoutGlobalScope(ScopesByBelongsToOwner::class)
                ->where('status', OfferStatus::Published)
                ->where('visibility', OfferVisibility::Public)
                ->whereSiteVerified()
                ->when(mb_strlen((string) $search) >= 3, function (Builder $query) use ($search): Builder {
                    $pattern = LikeSearch::contains((string) $search);

                    return $query->where(function (Builder $q) use ($pattern): void {
                        LikeSearch::whereLike($q, 'name', $pattern);
                        LikeSearch::orWhereLike($q, 'description', $pattern);
                    });
                })
                ->when($this->categoryFilter, fn (Builder $query) => $query->where('category_id', $this->categoryFilter))
                ->when($this->sortBy === 'featured', fn (Builder $query) => $query->orderByDesc('is_featured')->orderByDesc('created_at'))
                ->when($this->sortBy === 'newest', fn (Builder $query) => $query->orderByDesc('created_at'))
                ->when($this->sortBy === 'commission', fn (Builder $query) => $query->orderByDesc('rate_base_bp'))
                ->with([
                    'site' => fn ($query) => $query->withoutOwnerScope(),
                    'category' => fn ($query) => $query->withoutOwnerScope(),
                ])
                ->limit(50)
                ->get();
        });
    }

    public function getAffiliate(): ?NetworkAffiliate
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

        // Affiliate identity is resolved by email match: refuse to resolve for
        // users whose email is not verified so a changed-but-unverified email
        // cannot claim another affiliate's identity.
        if (! self::hasVerifiedEmail($user)) {
            return null;
        }

        $resolver = app(AffiliateIdentityResolver::class);

        // Public marketplace: find the user's affiliate regardless of owner — explicit global scope bypass.
        $affiliateId = OwnerContext::withOwner(null, fn (): ?string => $resolver->findIdForVerifiedEmail($email));

        $this->resolvedAffiliate = $affiliateId !== null
            ? OwnerContext::withOwner(null, fn () => $resolver->find($affiliateId))
            : null;

        return $this->resolvedAffiliate;
    }

    public function hasApplied(AffiliateOffer $offer): bool
    {
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            return false;
        }

        return $this->withAffiliateOwnerContext($affiliate, fn (): bool => app(OfferManagementService::class)
            ->hasAppliedForOffer($offer, $affiliate->id));
    }

    public function getApplicationStatus(AffiliateOffer $offer): ?string
    {
        $map = $this->getApplicationStatusMap();
        $offerId = (string) $offer->getKey();

        if (array_key_exists($offerId, $map)) {
            return $map[$offerId];
        }

        // Offer outside the rendered page: resolve through the same batched
        // builder for consistency with the page map instead of mixing in
        // OfferManagementService::applicationStatusForOffer() per card.
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            return null;
        }

        $single = $this->withAffiliateOwnerContext(
            $affiliate,
            fn (): array => app(OfferManagementService::class)
                ->applicationStatusMap($affiliate->id, new Collection([$offer]))
        );

        return $single[(string) $offer->getKey()] ?? null;
    }

    /**
     * Batch the per-card application statuses for the rendered page into a
     * fixed handful of queries (programs + memberships + applications) instead
     * of 1–3 queries per card.
     *
     * @return array<string, ?string> offer id => status value (null when never applied)
     */
    public function getApplicationStatusMap(): array
    {
        if ($this->applicationStatusMap !== null) {
            return $this->applicationStatusMap;
        }

        $map = [];
        $affiliate = $this->getAffiliate();
        $offers = $this->getOffers();

        if ($affiliate !== null && $offers->isNotEmpty()) {
            $map = $this->withAffiliateOwnerContext($affiliate, fn (): array => app(OfferManagementService::class)
                ->applicationStatusMap($affiliate->id, $offers));
        }

        return $this->applicationStatusMap = $map;
    }

    public function applyForOffer(string $offerId, string $reason = ''): void
    {
        $reason = mb_substr(mb_trim($reason), 0, 2000);

        if (! $this->hitMarketplaceThrottle('apply')) {
            Notification::make()
                ->title('Too many requests')
                ->body('Please wait a moment and try again.')
                ->warning()
                ->send();

            return;
        }

        $management = app(OfferManagementService::class);
        $offer = $management->resolvePublicOfferOrFail($offerId);

        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            if (! $offer->requires_approval) {
                $this->generateLink($offerId);

                return;
            }

            Notification::make()
                ->title('You must be an affiliate to apply')
                ->danger()
                ->send();

            return;
        }

        if ($management->isLocalProgramOffer($offer)) {
            $membership = $this->withAffiliateOwnerContext($affiliate, fn () => $management->enrollInLinkedProgram($offer, $affiliate->id));

            if ($membership === null) {
                Notification::make()
                    ->title('Linked program unavailable')
                    ->body('This offer is linked to a core program that is no longer available.')
                    ->danger()
                    ->send();

                return;
            }

            if (! $membership->isApproved()) {
                Notification::make()
                    ->title('Application submitted successfully')
                    ->success()
                    ->send();

                return;
            }

            $this->generateLink($offerId);

            return;
        }

        if (! $offer->requires_approval) {
            $this->generateLink($offerId);

            return;
        }

        if ($this->hasApplied($offer)) {
            Notification::make()
                ->title('You have already applied to this offer')
                ->warning()
                ->send();

            return;
        }

        $this->withAffiliateOwnerContext($affiliate, fn () => $management
            ->applyForOffer($offer, $affiliate->id, $reason));

        Notification::make()
            ->title('Application submitted successfully')
            ->success()
            ->send();
    }

    public function generateLink(string $offerId): void
    {
        if (! $this->hitMarketplaceThrottle('link')) {
            Notification::make()
                ->title('Too many requests')
                ->body('Please wait a moment and try again.')
                ->warning()
                ->send();

            return;
        }

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
            ->isApprovedForOffer($offer, $affiliate->id));

        if ($offer->requires_approval && ! $isApprovedForOffer) {
            Notification::make()
                ->title('Approval required')
                ->body('You must be approved for this offer before generating links.')
                ->danger()
                ->send();

            return;
        }

        $linkService = app(OfferLinkService::class);
        $link = $this->withAffiliateOwnerContext($affiliate, fn (): AffiliateOfferLink => $linkService->createLink($offer, $affiliate->id));
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
    private function withAffiliateOwnerContext(NetworkAffiliate $affiliate, callable $callback): mixed
    {
        return OwnerContext::withOwner($affiliate->owner(), $callback);
    }

    /**
     * Per-user throttle for the public state-changing actions (10 applies and
     * 30 link generations per minute). Falls back to the request IP for
     * guests, who are rejected downstream anyway.
     */
    private function hitMarketplaceThrottle(string $action): bool
    {
        $user = auth()->user();
        $identifier = $user?->getAuthIdentifier() ?? request()->ip();
        $key = sprintf('filament-affiliate-network.marketplace.%s.%s', $action, (string) $identifier);
        $maxAttempts = $action === 'apply' ? 10 : 30;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    /**
     * Whether the host user counts as email-verified.
     *
     * Hosts implementing MustVerifyEmail (or tracking email_verified_at) are
     * enforced; hosts with no verification concept cannot be gated here and
     * must verify emails before user-controlled changes or bind affiliates
     * to user ids instead of email matches.
     */
    private static function hasVerifiedEmail(Authenticatable $user): bool
    {
        if ($user instanceof MustVerifyEmail) {
            return $user->hasVerifiedEmail();
        }

        if ($user instanceof Model && array_key_exists('email_verified_at', $user->getAttributes())) {
            return $user->getAttribute('email_verified_at') !== null;
        }

        return true;
    }
}
