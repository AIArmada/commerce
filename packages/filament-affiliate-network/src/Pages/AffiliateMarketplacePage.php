<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Pages;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
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

    private ?Affiliate $resolvedAffiliate = null;

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
                ->when(mb_strlen((string) $search) >= 3, function (Builder $query) use ($search): Builder {
                    $escaped = str_replace(['%', '_'], ['\%', '\_'], (string) $search);
                    $operator = match (ConnectionDriver::name($query->getConnection())) {
                        'pgsql' => 'ilike',
                        default => 'like',
                    };

                    return $query->where(function (Builder $q) use ($escaped, $operator): void {
                        $q->where('name', $operator, "%{$escaped}%")
                            ->orWhere('description', $operator, "%{$escaped}%");
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

        // Affiliate identity is resolved by email match: refuse to resolve for
        // users whose email is not verified so a changed-but-unverified email
        // cannot claim another affiliate's identity.
        if (! self::hasVerifiedEmail($user)) {
            return null;
        }

        // Public marketplace: find the user's affiliate regardless of owner — explicit global scope bypass.
        // contact_email is a virtual attribute stored in contact_methods, not a DB column.
        $this->resolvedAffiliate = OwnerContext::withOwner(null, fn (): ?Affiliate => Affiliate::query()
            ->withoutOwnerScope()
            ->whereHas('contactMethods', function (Builder $query) use ($email): void {
                $query->withoutGlobalScope(OwnerScope::class)
                    ->where('type', 'email')
                    ->where('purpose', 'general')
                    ->where(fn (Builder $q) => $q->where('value', $email)
                        ->orWhere('normalized_value', $email));
            })
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

        return $this->withAffiliateOwnerContext($affiliate, fn (): bool => app(OfferManagementService::class)
            ->hasAppliedForOffer($offer, $affiliate));
    }

    public function getApplicationStatus(AffiliateOffer $offer): ?string
    {
        $map = $this->getApplicationStatusMap();
        $offerId = (string) $offer->getKey();

        if (array_key_exists($offerId, $map)) {
            return $map[$offerId];
        }

        // Offer outside the rendered page: resolve through the same batched
        // builder instead of OfferManagementService::applicationStatusForOffer(),
        // whose application-table path returns the enum (not ?string) and
        // TypeErrors. See the cross-package follow-up on affiliate-network.
        $affiliate = $this->getAffiliate();

        if ($affiliate === null) {
            return null;
        }

        $single = $this->withAffiliateOwnerContext(
            $affiliate,
            fn (): array => $this->buildApplicationStatusMap($affiliate, new Collection([$offer]))
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
            $map = $this->withAffiliateOwnerContext($affiliate, fn (): array => $this->buildApplicationStatusMap($affiliate, $offers));
        }

        return $this->applicationStatusMap = $map;
    }

    /**
     * @param  Collection<int, AffiliateOffer>  $offers
     * @return array<string, ?string>
     */
    private function buildApplicationStatusMap(Affiliate $affiliate, Collection $offers): array
    {
        $management = app(OfferManagementService::class);

        /** @var array<string, array<int, string>> $programOfferIds program id => offer ids */
        $programOfferIds = [];
        /** @var array<int, string> $networkOfferIds */
        $networkOfferIds = [];

        /** @var array<string, ?string> $map */
        $map = [];

        foreach ($offers as $offer) {
            $offerId = (string) $offer->getKey();
            $map[$offerId] = null;

            if ($management->isLocalProgramOffer($offer)) {
                $programOfferIds[(string) $offer->external_program_id][] = $offerId;
            } else {
                $networkOfferIds[] = $offerId;
            }
        }

        if ($programOfferIds !== []) {
            /** @var array<int, string> $existingIds */
            $existingIds = AffiliateProgram::query()
                ->whereIn('id', array_keys($programOfferIds))
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();

            $existing = array_fill_keys($existingIds, true);

            /** @var array<string, ?string> $statusByProgram */
            $statusByProgram = [];

            foreach (AffiliateProgramMembership::query()
                ->where('affiliate_id', $affiliate->getKey())
                ->whereIn('program_id', $existingIds)
                ->pluck('status', 'program_id') as $programId => $status) {
                $statusByProgram[(string) $programId] = $status instanceof BackedEnum ? $status->value : (string) $status;
            }

            foreach ($programOfferIds as $programId => $offerIds) {
                if (! isset($existing[$programId])) {
                    // Missing local program: same fallback as the domain service —
                    // resolve through the network application flow instead.
                    array_push($networkOfferIds, ...$offerIds);

                    continue;
                }

                foreach ($offerIds as $offerId) {
                    $map[$offerId] = $statusByProgram[$programId] ?? null;
                }
            }
        }

        if ($networkOfferIds !== []) {
            foreach (AffiliateOfferApplication::query()
                ->where('affiliate_id', $affiliate->getKey())
                ->whereIn('offer_id', $networkOfferIds)
                ->pluck('status', 'offer_id') as $offerId => $status) {
                $map[(string) $offerId] = $status instanceof BackedEnum ? $status->value : (string) $status;
            }
        }

        return $map;
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
            $membership = $this->withAffiliateOwnerContext($affiliate, fn () => $management->enrollInLinkedProgram($offer, $affiliate));

            if ($membership === null) {
                Notification::make()
                    ->title('Linked program unavailable')
                    ->body('This offer is linked to a core program that is no longer available.')
                    ->danger()
                    ->send();

                return;
            }

            if ($membership->status !== MembershipStatus::Approved) {
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
            ->applyForOffer($offer, $affiliate, $reason));

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
