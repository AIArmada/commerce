<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliation;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliationQueries;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class PayoutReconciliationQuery implements PayoutReconciliationQueries
{
    public function __construct(private CurrencyConverter $converter) {}

    /** @return list<string> */
    public function payoutIdsNeedingReconciliation(): array
    {
        return AffiliatePayout::query()
            ->whereIn('status', [ProcessingPayout::value(), PendingPayout::value()])
            ->where(static function ($query): void {
                $query->whereHas('operation', static function ($operationQuery): void {
                    $operationQuery->whereIn('status', ['submitting', 'submitted', 'unknown']);
                })->orWhereNotNull('external_reference');
            })
            ->where('updated_at', '<=', CarbonImmutable::now()->subMinutes(5))
            ->whereDoesntHave('operation', static fn ($query) => $query->where('last_error_code', PayoutReconciliation::COMPLETION_BLOCKED_CODE))
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()->all();
    }

    /** @return array<string, mixed> */
    public function generateReport(?string $startDate = null, ?string $endDate = null): array
    {
        $query = AffiliatePayout::query();

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('created_at', '<=', $endDate);
        }

        return $this->summarizePayouts($query->get(), $startDate, $endDate);
    }

    public function summarizePayoutIds(array $payoutIds, ?string $startDate = null, ?string $endDate = null, bool $includeGlobal = false): array
    {
        $ids = array_values(array_unique($payoutIds));
        $payouts = AffiliatePayout::query()->forOwner(OwnerContext::CURRENT, $includeGlobal)->whereKey($ids)->get();

        if ($payouts->count() !== count($ids)) {
            throw new AuthorizationException('One or more payouts are not accessible.');
        }

        return $this->summarizePayouts($payouts, $startDate, $endDate);
    }

    /**
     * Settlement summary over an explicit payout set: per-currency legs
     * plus one labeled converted total with rate provenance.
     *
     * @param  Collection<int, AffiliatePayout>  $payouts
     */
    private function summarizePayouts(Collection $payouts, ?string $startDate = null, ?string $endDate = null): array
    {
        $byStatus = $payouts->groupBy(fn (AffiliatePayout $item): string => $item->status->getValue())->map->count();
        $completed = $payouts->filter(fn (AffiliatePayout $item): bool => $item->status->equals(CompletedPayout::class));
        $failed = $payouts->filter(fn (AffiliatePayout $item): bool => $item->status->equals(FailedPayout::class));
        $byCurrency = $payouts
            ->groupBy(fn (AffiliatePayout $item): string => mb_strtoupper((string) $item->currency))
            ->map(fn (Collection $group): array => [
                'count' => $group->count(),
                'total_minor' => (int) $group->sum('total_minor'),
            ])
            ->all();

        $asOf = $endDate !== null ? CarbonImmutable::parse($endDate) : CarbonImmutable::now();
        $summary = $this->summarizeAmounts($payouts, $completed, $failed, $asOf);

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_payouts' => $payouts->count(),
                'total_amount_minor' => $summary['total'],
                'completed_amount_minor' => $summary['completed'],
                'failed_amount_minor' => $summary['failed'],
                'pending_amount_minor' => $summary['pending'],
                'currency' => $summary['currency'],
                'converted' => $summary['converted'],
                'conversion' => $summary['conversion'],
            ],
            'by_status' => $byStatus->all(),
            'by_currency' => $byCurrency,
            'discrepancies' => $this->findDiscrepancies($payouts),
            'currency_mismatches' => $this->findCurrencyMismatches($payouts),
        ];
    }

    /**
     * Fold payout amounts without blending currencies: single-currency sets
     * pass raw sums through, mixed sets convert to the package default, and
     * legs without a rate null the whole total instead of guessing.
     *
     * @param  Collection<int, AffiliatePayout>  $payouts
     * @param  Collection<int, AffiliatePayout>  $completed
     * @param  Collection<int, AffiliatePayout>  $failed
     * @return array{total: int|null, completed: int|null, failed: int|null, pending: int|null, currency: string, converted: bool}
     */
    /**
     * @return array{total: int|null, completed: int|null, failed: int|null, pending: int|null, currency: string, converted: bool, conversion: array{currency: string, as_of: string, source: string}|null}
     */
    private function summarizeAmounts(Collection $payouts, Collection $completed, Collection $failed, ?DateTimeInterface $asOf = null): array
    {
        $default = mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));
        $currencies = $payouts
            ->map(fn (AffiliatePayout $payout): string => mb_strtoupper((string) $payout->currency))
            ->unique()
            ->values();

        if ($currencies->count() <= 1) {
            $total = (int) $payouts->sum('total_minor');
            $completedAmount = (int) $completed->sum('total_minor');
            $failedAmount = (int) $failed->sum('total_minor');

            return [
                'total' => $total,
                'completed' => $completedAmount,
                'failed' => $failedAmount,
                'pending' => $total - $completedAmount - $failedAmount,
                'currency' => $currencies->first() ?? $default,
                'converted' => false,
                'conversion' => null,
            ];
        }

        $total = $this->converter->totalMinor($this->amountsByCurrency($payouts), $default, $asOf);
        $completedAmount = $this->converter->totalMinor($this->amountsByCurrency($completed), $default, $asOf);
        $failedAmount = $this->converter->totalMinor($this->amountsByCurrency($failed), $default, $asOf);
        $converted = $total !== null && $completedAmount !== null && $failedAmount !== null;

        return [
            'total' => $total,
            'completed' => $completedAmount,
            'failed' => $failedAmount,
            'pending' => $total !== null && $completedAmount !== null && $failedAmount !== null
                ? $total - $completedAmount - $failedAmount
                : null,
            'currency' => $default,
            'converted' => $converted,
            'conversion' => $converted ? $this->converter->conversionDisclosure($default, $asOf) : null,
        ];
    }

    /**
     * @param  Collection<int, AffiliatePayout>  $payouts
     * @return array<string, int>
     */
    private function amountsByCurrency(Collection $payouts): array
    {
        $byCurrency = [];

        foreach ($payouts as $payout) {
            $currency = mb_strtoupper((string) $payout->currency);

            $byCurrency[$currency] = ($byCurrency[$currency] ?? 0) + (int) $payout->total_minor;
        }

        return $byCurrency;
    }

    /** @return array<string, array<string, int|string|bool>> */
    public function auditAllAffiliateBalances(string $affiliateId): array
    {
        $affiliate = Affiliate::query()->forOwner()->findOrFail($affiliateId);
        $currencies = $affiliate->balances()->pluck('currency')->unique()->values()->all();

        $audits = [];

        foreach ($currencies as $currency) {
            $audits[(string) $currency] = $this->auditBalance($affiliate, (string) $currency);
        }

        return $audits;
    }

    /** @return array<string, int|string|bool> */
    public function auditAffiliateBalance(string $affiliateId, string $currency): array
    {
        return $this->auditBalance(Affiliate::query()->forOwner()->findOrFail($affiliateId), $currency);
    }

    /** @return array<string, int|string|bool> */
    private function auditBalance(Affiliate $affiliate, string $currency): array
    {
        $currency = mb_strtoupper($currency);
        $approvedCommissions = (int) $affiliate->conversions()->where('status', ApprovedConversion::value())->where('commission_currency', $currency)->sum('commission_minor');
        $completedPayoutConversions = (int) $affiliate->conversions()
            ->where('status', PaidConversion::value())
            ->where('commission_currency', $currency)
            ->whereHas('payout', static fn (Builder $query): Builder => $query
                ->where('status', CompletedPayout::value())
                ->where('currency', $currency))
            ->sum('commission_minor');
        $directPaidClawbacks = (int) $affiliate->conversions()
            ->where('status', ReversedConversion::value())
            ->whereNull('affiliate_payout_id')
            ->whereNotNull('paid_at')
            ->where('commission_currency', $currency)
            ->sum('commission_minor');
        $reservedOperations = AffiliatePayoutOperation::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->where('currency', $currency)
            ->whereNotNull('payout_sequence')
            ->whereNull('funds_released_at');
        $paidOut = (int) (clone $reservedOperations)
            ->whereHas('payout', static fn (Builder $query): Builder => $query->where('status', CompletedPayout::value()))
            ->sum('amount_minor');
        $pendingPayouts = (int) (clone $reservedOperations)
            ->whereHas('payout', static fn (Builder $query): Builder => $query->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()]))
            ->sum('amount_minor');
        $expectedAvailable = $approvedCommissions + $completedPayoutConversions - $paidOut - $pendingPayouts - $directPaidClawbacks;
        $actualAvailable = $affiliate->balanceFor($currency)?->available_minor ?? 0;
        $discrepancy = $expectedAvailable - $actualAvailable;

        return [
            'affiliate_id' => (string) $affiliate->id,
            'currency' => $currency,
            'expected_available_minor' => $expectedAvailable,
            'actual_available_minor' => $actualAvailable,
            'discrepancy_minor' => $discrepancy,
            'has_discrepancy' => $discrepancy !== 0,
            'approved_commissions_minor' => $approvedCommissions,
            'completed_payout_conversions_minor' => $completedPayoutConversions,
            'direct_paid_clawbacks_minor' => $directPaidClawbacks,
            'paid_out_minor' => $paidOut,
            'pending_payouts_minor' => $pendingPayouts,
        ];
    }

    /** @return list<array<string, string>> */
    private function findCurrencyMismatches(Collection $payouts): array
    {
        $mismatches = [];

        foreach ($payouts as $payout) {
            $expected = mb_strtoupper((string) $payout->currency);
            $actual = $payout->conversions()
                ->pluck('commission_currency')
                ->map(fn (mixed $code): string => mb_strtoupper((string) $code))
                ->unique()
                ->values();

            if ($actual->count() !== 1 || $actual->first() !== $expected) {
                $mismatches[] = [
                    'payout_id' => (string) $payout->id,
                    'payout_currency' => $expected,
                    'conversion_currencies' => $actual->implode(','),
                ];
            }
        }

        return $mismatches;
    }

    /** @return list<array<string, int|string>> */
    private function findDiscrepancies(Collection $payouts): array
    {
        $discrepancies = [];

        foreach ($payouts as $payout) {
            $linkedAmount = (int) $payout->conversions()->sum('commission_minor');

            if ($linkedAmount !== $payout->total_minor) {
                $discrepancies[] = [
                    'payout_id' => (string) $payout->id,
                    'payout_amount_minor' => $payout->total_minor,
                    'linked_commissions_minor' => $linkedAmount,
                    'difference_minor' => $payout->total_minor - $linkedAmount,
                ];
            }
        }

        return $discrepancies;
    }
}
