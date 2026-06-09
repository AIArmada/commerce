<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateFromCookie;
use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateToCart;
use AIArmada\Affiliates\Actions\Affiliates\CreateTrackingLink;
use AIArmada\Affiliates\Actions\Affiliates\TouchAffiliateAttribution;
use AIArmada\Affiliates\Actions\Affiliates\TrackAffiliateVisit;
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateConversion;
use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Cart\Cart;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;

final class AffiliateService
{
    public function __construct(
        private readonly AttachAffiliateToCart $attachAffiliateToCart,
        private readonly AttachAffiliateFromCookie $attachAffiliateFromCookie,
        private readonly CreateTrackingLink $createTrackingLink,
        private readonly TrackAffiliateVisit $trackAffiliateVisit,
        private readonly TouchAffiliateAttribution $touchAffiliateAttribution,
        private readonly RecordAffiliateConversion $recordAffiliateConversion,
    ) {}

    /**
     * @return Builder<Affiliate>
     */
    public function query(): Builder
    {
        $query = Affiliate::query();

        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        return $query->forOwner();
    }

    public function findByCode(string $code): ?Affiliate
    {
        $normalized = mb_trim($code);
        $query = $this->query();
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $driver = ConnectionDriver::name($connection);

        /** @var Affiliate|null */
        return $query
            ->when(
                $driver === 'pgsql',
                fn ($q) => $q->whereRaw('code ILIKE ?', [$normalized]),
                fn ($q) => $q->whereRaw('LOWER(code) = ?', [mb_strtolower($normalized)])
            )
            ->first();
    }

    public function findByCodeWithoutOwnerScope(string $code): ?Affiliate
    {
        $normalized = mb_trim($code);
        $query = Affiliate::query()->withoutOwnerScope();

        /** @var Connection $connection */
        $connection = $query->getConnection();
        $driver = ConnectionDriver::name($connection);

        /** @var Affiliate|null */
        return $query
            ->when(
                $driver === 'pgsql',
                fn ($q) => $q->whereRaw('code ILIKE ?', [$normalized]),
                fn ($q) => $q->whereRaw('LOWER(code) = ?', [mb_strtolower($normalized)])
            )
            ->first();
    }

    public function findByDefaultVoucherCode(string $voucherCode): ?Affiliate
    {
        $normalized = mb_trim($voucherCode);
        $query = $this->query();
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $driver = ConnectionDriver::name($connection);

        /** @var Affiliate|null */
        return $query
            ->when(
                $driver === 'pgsql',
                fn ($q) => $q->whereRaw('default_voucher_code ILIKE ?', [$normalized]),
                fn ($q) => $q->whereRaw('LOWER(default_voucher_code) = ?', [mb_strtolower($normalized)])
            )
            ->first();
    }

    public function findAffiliateByCookie(string $cookieValue): ?Affiliate
    {
        $attribution = $this->findAttributionByCookie($cookieValue);
        $affiliate = $attribution?->affiliate;

        if (! $affiliate || ! $affiliate->isActive()) {
            return null;
        }

        return $affiliate;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTrackingLink(Affiliate $affiliate, string $destinationUrl, array $attributes = []): AffiliateLink
    {
        return $this->createTrackingLink->handle($affiliate, $destinationUrl, $attributes);
    }

    public function attachToCartByCode(string $code, Cart $cart, array $context = []): ?AffiliateAttributionData
    {
        $affiliate = $this->findByCode($code);

        if (! $affiliate || ! $affiliate->isActive()) {
            return null;
        }

        return $this->attachAffiliateToCart->handle($affiliate, $cart, $context);
    }

    public function attachAffiliate(Affiliate $affiliate, Cart $cart, array $context = []): ?AffiliateAttributionData
    {
        return $this->attachAffiliateToCart->handle($affiliate, $cart, $context);
    }

    public function attachAffiliateFromCookie(Cart $cart, string $cookieValue, array $context = []): ?AffiliateAttributionData
    {
        return $this->attachAffiliateFromCookie->handle($cart, $cookieValue, $context);
    }

    public function trackVisitByCode(string $code, array $context = [], ?string $cookieValue = null): ?AffiliateAttributionData
    {
        return $this->trackAffiliateVisit->handle($code, $context, $cookieValue);
    }

    public function touchCookieAttribution(string $cookieValue, array $context = []): ?AffiliateAttributionData
    {
        return $this->touchAffiliateAttribution->handle($cookieValue, $context);
    }

    public function detachFromCart(Cart $cart): void
    {
        $cart->removeMetadata((string) config('affiliates.cart.metadata_key', 'affiliate'));
    }

    public function getAttachedAffiliate(Cart $cart): ?AffiliateData
    {
        $payload = $cart->getMetadata((string) config('affiliates.cart.metadata_key', 'affiliate'));

        if (! is_array($payload)) {
            return null;
        }

        $affiliate = null;

        if (isset($payload['affiliate_id'])) {
            /** @var Affiliate|null $affiliate */
            $affiliate = $this->query()->find($payload['affiliate_id']);
        }

        if (! $affiliate && isset($payload['affiliate_code'])) {
            $affiliate = $this->findByCode((string) $payload['affiliate_code']);
        }

        if (! $affiliate) {
            return null;
        }

        return AffiliateData::fromModel($affiliate);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordConversion(Cart $cart, array $payload = []): ?AffiliateConversionData
    {
        return $this->recordAffiliateConversion->handle($cart, $payload);
    }

    private function findAttributionByCookie(?string $cookieValue): ?AffiliateAttribution
    {
        $cookieCandidates = $this->resolveCookieCandidates($cookieValue);

        if ($cookieCandidates === []) {
            return null;
        }

        $query = AffiliateAttribution::query()
            ->with('affiliate')
            ->whereIn('cookie_value', $cookieCandidates)
            ->active()
            ->latest('last_cookie_seen_at');

        return $query->first();
    }

    /**
     * @return array<int, string>
     */
    private function resolveCookieCandidates(?string $cookieValue): array
    {
        if (! is_string($cookieValue) || $cookieValue === '') {
            return [];
        }

        $candidates = [$cookieValue];

        try {
            $decryptedCookieValue = Crypt::decryptString($cookieValue);

            if ($decryptedCookieValue !== '') {
                $candidates[] = $decryptedCookieValue;
            }
        } catch (DecryptException) {
        }

        return array_values(array_unique($candidates));
    }
}
