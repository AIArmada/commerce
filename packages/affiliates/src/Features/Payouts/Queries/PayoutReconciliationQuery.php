<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\BalanceReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReferenceReader;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliation;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliationQueries;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\States\CompletedPayout;
use AIArmada\Affiliates\Features\Payouts\States\FailedPayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

final readonly class PayoutReconciliationQuery implements PayoutReconciliationQueries
{
    public function __construct(private CurrencyConverter $converter, private BalanceReader $balances, private PayoutConversionIntegrity $conversions, private ConversionStatisticsReader $statistics) {}

    /** @return list<string> */
    public function payoutIdsNeedingReconciliation(): array
    {
        return AffiliatePayout::query()
            ->whereIn('status', [ProcessingPayout::value(), PendingPayout::value()])
            ->where(static function ($query): void {
                $query->whereIn('affiliate_payout_operation_id', AffiliatePayoutOperation::query()->whereIn('status', ['submitting', 'submitted', 'unknown'])->select('id'))
                    ->orWhereNotNull('external_reference');
            })
            ->where('updated_at', '<=', CarbonImmutable::now()->subMinutes(5))
            ->where(static fn ($query) => $query->whereNull('affiliate_payout_operation_id')->orWhereNotIn('affiliate_payout_operation_id', AffiliatePayoutOperation::query()->where('last_error_code', PayoutReconciliation::COMPLETION_BLOCKED_CODE)->select('id')))
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

        foreach ($ids as $id) {
            if (! Str::isUuid($id)) {
                throw new AuthorizationException('One or more payouts are not accessible.');
            }
        }

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

        $payoutOwners = [];

        foreach ($payouts as $payout) {
            $payoutOwners[$payout->id] = ['owner_type' => $payout->owner_type, 'owner_id' => $payout->owner_id];
        }

        $conversionTotals = $this->conversions->reportTotals($payoutOwners, spl_object_id((new AffiliatePayout)->getConnection()));

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
            'discrepancies' => $this->findDiscrepancies($payouts, $conversionTotals),
            'currency_mismatches' => $this->findCurrencyMismatches($payouts, $conversionTotals),
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
        $identity = $this->assertAuditIdentity($affiliateId);
        $currencies = array_map(static fn ($balance): string => $balance->currency, $this->balances->forAffiliate($affiliateId));

        $audits = [];

        foreach ($currencies as $currency) {
            $audits[(string) $currency] = $this->auditBalance($affiliateId, (string) $currency, $identity);
        }

        return $audits;
    }

    /** @return array<string, int|string|bool> */
    public function auditAffiliateBalance(string $affiliateId, string $currency): array
    {
        return $this->auditBalance($affiliateId, $currency, $this->assertAuditIdentity($affiliateId));
    }

    private function assertAuditIdentity(string $affiliateId): int
    {
        $connection = (new AffiliatePayout)->getConnection();
        $identity = spl_object_id($connection);

        if ((new AffiliatePayoutOperation)->getConnection() !== $connection) {
            throw new LogicException('Balance reconciliation requires a shared database connection.');
        }

        if (app(AffiliateReferenceReader::class)->ownerReference($affiliateId, $identity) === null) {
            throw new ModelNotFoundException('Affiliate not found.');
        }

        return $identity;
    }

    /** @return array<string, int|string|bool> */
    private function auditBalance(string $affiliateId, string $currency, int $connectionIdentity): array
    {
        $currency = mb_strtoupper($currency);
        $audit = $this->statistics->balanceAudit($affiliateId, $currency, $connectionIdentity);
        $approvedCommissions = $audit->approvedMinor;
        $directPaidClawbacks = $audit->directPaidClawbacksMinor;
        $completedPayoutConversions = 0;

        foreach (array_chunk(array_keys($audit->paidByPayout), 500) as $ids) {
            $completed = AffiliatePayout::query()->forOwner()
                ->whereKey($ids)
                ->where('status', CompletedPayout::value())
                ->whereRaw('UPPER(currency) = ?', [$currency])
                ->pluck('id');

            foreach ($completed as $id) {
                $completedPayoutConversions += $audit->paidByPayout[(string) $id];
            }
        }

        $reservedOperations = AffiliatePayoutOperation::query()
            ->where('affiliate_id', $affiliateId)
            ->whereRaw('UPPER(currency) = ?', [$currency])
            ->whereNotNull('payout_sequence')
            ->whereNull('funds_released_at');
        $paidOut = (int) (clone $reservedOperations)
            ->whereHas('payout', static fn (Builder $query): Builder => $query->where('status', CompletedPayout::value()))
            ->sum('amount_minor');
        $pendingPayouts = (int) (clone $reservedOperations)
            ->whereHas('payout', static fn (Builder $query): Builder => $query->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()]))
            ->sum('amount_minor');
        $expectedAvailable = $approvedCommissions + $completedPayoutConversions - $paidOut - $pendingPayouts - $directPaidClawbacks;
        $actualAvailable = $this->balances->find($affiliateId, $currency)?->availableMinor ?? 0;
        $discrepancy = $expectedAvailable - $actualAvailable;

        return [
            'affiliate_id' => $affiliateId,
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
    private function findCurrencyMismatches(Collection $payouts, array $conversionTotals): array
    {
        $mismatches = [];

        foreach ($payouts as $payout) {
            $expected = mb_strtoupper((string) $payout->currency);
            $actual = $conversionTotals[$payout->id]['currencies'] ?? [];

            if ($actual !== [$expected]) {
                $mismatches[] = [
                    'payout_id' => (string) $payout->id,
                    'payout_currency' => $expected,
                    'conversion_currencies' => implode(',', $actual),
                ];
            }
        }

        return $mismatches;
    }

    /** @return list<array<string, int|string>> */
    private function findDiscrepancies(Collection $payouts, array $conversionTotals): array
    {
        $discrepancies = [];

        foreach ($payouts as $payout) {
            $linkedAmount = $conversionTotals[$payout->id]['total_minor'] ?? 0;

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
