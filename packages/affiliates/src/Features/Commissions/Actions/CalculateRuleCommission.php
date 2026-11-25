<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Actions;

use AIArmada\Affiliates\Features\Commissions\Api\CommissionCalculationResult;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionPricing;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionRuleCalculation;
use AIArmada\Affiliates\Features\Commissions\Queries\FindCommissionRules;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Support\RevenueVolume;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;

final readonly class CalculateRuleCommission implements CommissionRuleCalculation
{
    public function __construct(
        private FindCommissionRules $rules,
        private CommissionPricing $pricing,
        private ConsumePromotionUsage $promotionUsage,
    ) {}

    /**
     * Calculate the commission for a conversion.
     *
     * @param  array<string, mixed>  $context
     */
    public function calculate(
        string $affiliateId,
        int $orderAmountMinor,
        array $context = []
    ): CommissionCalculationResult {
        $connection = (new Affiliate)->getConnection();

        if ($connection !== (new AffiliateCommissionPromotion)->getConnection()
            || $connection !== (new AffiliateCommissionRule)->getConnection()
            || $connection !== (new AffiliateVolumeTier)->getConnection()
            || $connection !== (new AffiliateConversion)->getConnection()) {
            throw new LogicException('Commission calculation requires a shared database connection.');
        }

        return $connection->transaction(function () use ($affiliateId, $orderAmountMinor, $context): CommissionCalculationResult {
            $affiliate = Affiliate::query()->forOwner(includeGlobal: true)->lockForUpdate()->findOrFail($affiliateId);

            return $this->calculateForAffiliate($affiliate, $orderAmountMinor, $context);
        });
    }

    /** @param array<string, mixed> $context */
    private function calculateForAffiliate(Affiliate $affiliate, int $orderAmountMinor, array $context): CommissionCalculationResult
    {
        $context = array_merge($context, [
            'affiliate_id' => $affiliate->id,
            'order_amount_minor' => $orderAmountMinor,
        ]);

        // Get applicable rules ordered by priority
        $rules = $this->rules->matchingRules($context);

        // Calculate base commission from highest priority matching rule
        $baseCommission = $this->calculateBaseCommission($rules, $orderAmountMinor);

        // Apply volume tier if applicable
        $volumeBonus = $this->calculateVolumeBonus($affiliate, $orderAmountMinor, $context);

        // Apply promotions
        $promotionBonus = $this->calculatePromotionBonus($affiliate, $baseCommission, $context);

        // Apply caps
        $finalCommission = $this->pricing->clamp($baseCommission + $volumeBonus + $promotionBonus);

        return new CommissionCalculationResult(
            baseCommissionMinor: $baseCommission,
            volumeBonusMinor: $volumeBonus,
            promotionBonusMinor: $promotionBonus,
            finalCommissionMinor: $finalCommission,
            appliedRules: $rules->pluck('id')->all(),
            metadata: [
                'order_amount_minor' => $orderAmountMinor,
                'context' => $context,
            ]
        );
    }

    /**
     * @return Collection<int, AffiliateVolumeTier>
     */
    private function volumeTiersForProgram(mixed $programId): Collection
    {
        return AffiliateVolumeTier::query()
            ->when(
                $programId,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($programId): void {
                    $inner->whereNull('program_id')->orWhere('program_id', $programId);
                }),
                fn (Builder $query) => $query->whereNull('program_id')
            )
            ->orderBy('min_volume_minor')
            ->get();
    }

    /**
     * @return Collection<int, AffiliateCommissionPromotion>
     */
    private function promotionsForProgram(mixed $programId): Collection
    {
        return AffiliateCommissionPromotion::query()
            ->active()
            ->when(
                $programId,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($programId): void {
                    $inner->whereNull('program_id')->orWhere('program_id', $programId);
                }),
                fn (Builder $query) => $query->whereNull('program_id')
            )
            ->get();
    }

    /**
     * @param  Collection<int, AffiliateCommissionRule>  $rules
     */
    private function calculateBaseCommission(Collection $rules, int $orderAmountMinor): int
    {
        // Use the highest priority matching rule
        $rule = $rules->first();

        if (! $rule) {
            // Fall back to default config rate
            $defaultRateBasisPoints = (int) config('affiliates.commissions.default_rate', 1000);

            return (int) round($orderAmountMinor * $defaultRateBasisPoints / 10000);
        }

        return $rule->calculateCommission($orderAmountMinor);
    }

    private function calculateVolumeBonus(Affiliate $affiliate, int $orderAmountMinor, array $context): int
    {
        $programId = $context['program_id'] ?? null;

        // Get affiliate's volume for the period
        $volumeQuery = $affiliate->conversions();

        if ($programId) {
            $volumeQuery->where(function (Builder $query) use ($programId): void {
                $query
                    ->whereHas('affiliateLink', fn (Builder $linkQuery) => $linkQuery->where('program_id', $programId))
                    ->orWhereHas('attribution.affiliateLink', fn (Builder $linkQuery) => $linkQuery->where('program_id', $programId));
            });
        }

        $volumeRows = $volumeQuery
            ->where('occurred_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->toBase()
            ->selectRaw('commission_currency as currency, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as total')
            ->groupBy('commission_currency')
            ->get();

        $reference = RevenueVolume::referenceFor($affiliate);
        $folded = RevenueVolume::foldRows($volumeRows, $reference);
        $volumeIn = [];

        $volumeFor = function (string $currency) use ($folded, &$volumeIn): int {
            return $volumeIn[$currency] ??= RevenueVolume::measurableIn($folded, $currency);
        };

        // Tiers qualify in their own currency; mixed-currency tiers order by
        // converted floor with unconvertible tiers sinking last.
        $tier = $this->volumeTiersForProgram($programId)
            ->filter(fn (AffiliateVolumeTier $candidate): bool => $candidate->containsVolume($volumeFor($candidate->currencyCode())))
            ->sort(function (AffiliateVolumeTier $left, AffiliateVolumeTier $right) use ($reference): int {
                $converter = app(CurrencyConverter::class);
                $leftMin = $converter->convertMinor($left->min_volume_minor, $left->currencyCode(), $reference);
                $rightMin = $converter->convertMinor($right->min_volume_minor, $right->currencyCode(), $reference);

                if ($leftMin === null && $rightMin === null) {
                    return 0;
                }

                if ($leftMin === null) {
                    return 1;
                }

                if ($rightMin === null) {
                    return -1;
                }

                return $rightMin <=> $leftMin;
            })
            ->first();

        if (! $tier) {
            return 0;
        }

        // Calculate bonus based on volume tier rate
        $tierCommission = (int) round($orderAmountMinor * $tier->commission_rate_basis_points / 10000);
        $defaultRateBasisPoints = (int) config('affiliates.commissions.default_rate', 1000);
        $defaultCommission = (int) round($orderAmountMinor * $defaultRateBasisPoints / 10000);

        // Return the difference as bonus
        return max(0, $tierCommission - $defaultCommission);
    }

    private function calculatePromotionBonus(Affiliate $affiliate, int $baseCommission, array $context): int
    {
        $programId = $context['program_id'] ?? null;

        $promotions = $this->promotionsForProgram($programId)
            ->filter(fn (AffiliateCommissionPromotion $promo) => $promo->appliesToAffiliate($affiliate));

        $totalBonus = 0;

        foreach ($promotions as $promotion) {
            if ($this->promotionUsage->consume((string) $promotion->getKey())) {
                $totalBonus += $promotion->calculateBonus($baseCommission);
            }
        }

        return $totalBonus;
    }

}
