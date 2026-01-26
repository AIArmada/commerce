<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Concerns;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Collection;

trait InteractsWithAffiliate
{
    protected ?Affiliate $affiliate = null;

    /**
     * Get the current user's affiliate.
     *
     * In owner mode: looks up affiliate owned by the current tenant.
     * In non-owner mode: looks up affiliate where the user is the owner.
     */
    public function getAffiliate(): ?Affiliate
    {
        if ($this->affiliate !== null) {
            return $this->affiliate;
        }

        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            $owner = OwnerContext::resolve();

            $this->affiliate = Affiliate::query()
                ->forOwner($owner, false)
                ->where('owner_type', $user->getMorphClass())
                ->where('owner_id', $user->getKey())
                ->first();
        } else {
            $this->affiliate = Affiliate::query()
                ->where('owner_type', $user->getMorphClass())
                ->where('owner_id', $user->getKey())
                ->first();
        }

        return $this->affiliate;
    }

    /**
     * Check if the current user has an affiliate account.
     */
    public function hasAffiliate(): bool
    {
        return $this->getAffiliate() !== null;
    }

    /**
     * Get conversions for the affiliate.
     *
     * @return Collection<int, AffiliateConversion>
     */
    public function getConversions(int $limit = 10): Collection
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return new Collection;
        }

        return $affiliate->conversions()
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get payouts for the affiliate.
     *
     * @return Collection<int, AffiliatePayout>
     */
    public function getPayouts(int $limit = 10): Collection
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return new Collection;
        }

        return $affiliate->payouts()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get total earnings for the affiliate.
     */
    public function getTotalEarnings(): int
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return 0;
        }

        return (int) $affiliate->conversions()
            ->where('status', ApprovedConversion::value())
            ->sum('commission_minor');
    }

    /**
     * Get pending earnings for the affiliate.
     */
    public function getPendingEarnings(): int
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return 0;
        }

        return (int) $affiliate->conversions()
            ->where('status', PendingConversion::value())
            ->sum('commission_minor');
    }

    /**
     * Get total clicks/visits for the affiliate.
     */
    public function getTotalClicks(): int
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return 0;
        }

        return (int) $affiliate->attributions()->count();
    }

    /**
     * Get total conversions count for the affiliate.
     */
    public function getTotalConversions(): int
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return 0;
        }

        return (int) $affiliate->conversions()->count();
    }

    /**
     * Format amount for display.
     *
     * Uses the affiliate's currency or falls back to the default currency.
     * Formats with 2 decimal places which is standard for most currencies.
     */
    public function formatAmount(int $amount, ?string $currency = null): string
    {
        $affiliate = $this->getAffiliate();
        $currency = $currency ?? $affiliate?->currency ?? config('affiliates.currency.default', 'USD');

        // Determine decimal places based on currency (most use 2, some use 0)
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'IDR', 'CLP', 'PYG', 'UGX', 'RWF'];
        $decimals = in_array(mb_strtoupper($currency), $zeroDecimalCurrencies, true) ? 0 : 2;

        $divisor = $decimals === 0 ? 1 : 100;

        return $currency . ' ' . number_format($amount / $divisor, $decimals);
    }
}
