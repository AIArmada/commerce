<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PayoutReconciliationService
{
    public function __construct(private readonly CurrencyConverter $converter) {}

    /** @param array<string, mixed> $externalData */
    public function reconcilePayout(AffiliatePayout $payout, string $externalStatus, array $externalData = []): bool
    {
        $statusClass = $this->mapExternalStatus($externalStatus);

        if ($statusClass === null) {
            return false;
        }

        $changed = DB::transaction(function () use ($payout, $statusClass, $externalData, $externalStatus): bool {
            $locked = AffiliatePayout::query()->with('operation')->lockForUpdate()->find($payout->id);

            if (! $locked instanceof AffiliatePayout || $locked->status->equals($statusClass)) {
                return false;
            }

            // Provider events can arrive stale or out of order (including for
            // terminal payouts). Only declared state-machine transitions run;
            // anything else is ignored rather than forced.
            if (! $locked->status->canTransitionTo($statusClass)) {
                return false;
            }

            $fromStatus = $locked->status->getValue();
            $reference = isset($externalData['reference']) && is_string($externalData['reference'])
                ? mb_trim($externalData['reference'])
                : null;
            $providerStatus = isset($externalData['status']) && is_string($externalData['status'])
                ? mb_substr($externalData['status'], 0, 64)
                : mb_strtolower($externalStatus);
            $newStatus = PayoutStatus::fromString($statusClass, $locked);

            if ($newStatus->equals(CompletedPayout::class)) {
                $locked->assertHomogeneousConversions();
            }

            $metadata = array_merge($locked->metadata ?? [], array_filter([
                'reconciled_at' => CarbonImmutable::now()->toIso8601String(),
                'provider_status' => $providerStatus,
                'external_reference' => $reference,
                'external_data' => $externalData === [] ? null : $externalData,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            $locked->status->transitionTo($statusClass);

            $locked->forceFill([
                'paid_at' => $newStatus->equals(CompletedPayout::class) ? CarbonImmutable::now() : $locked->paid_at,
                'failed_at' => $newStatus->equals(FailedPayout::class) ? CarbonImmutable::now() : $locked->failed_at,
                'cancelled_at' => $newStatus->equals(CancelledPayout::class) ? CarbonImmutable::now() : $locked->cancelled_at,
                'metadata' => $metadata,
            ])->save();

            if ($locked->operation instanceof AffiliatePayoutOperation) {
                $locked->operation->forceFill([
                    'status' => match (true) {
                        $newStatus->equals(CompletedPayout::class) => 'completed',
                        $newStatus->equals(FailedPayout::class) => 'failed',
                        $newStatus->equals(CancelledPayout::class) => 'cancelled',
                        default => 'submitted',
                    },
                    'provider_reference' => $reference ?: $locked->operation->provider_reference,
                    'last_error_code' => $newStatus->equals(FailedPayout::class) ? 'PROVIDER_RECONCILED_FAILURE' : null,
                    'completed_at' => $newStatus->equals(CompletedPayout::class) || $newStatus->equals(FailedPayout::class) || $newStatus->equals(CancelledPayout::class) ? CarbonImmutable::now() : null,
                    'lease_expires_at' => null,
                ])->save();
            }

            $this->syncConversions($locked, $newStatus);

            $locked->events()->create([
                'from_status' => $fromStatus,
                'to_status' => $newStatus->getValue(),
                'notes' => 'Provider status reconciled to ' . $newStatus->getValue(),
                'metadata' => ['provider_status' => $providerStatus],
            ]);

            return true;
        }, attempts: 3);

        return $changed;
    }

    private function syncConversions(AffiliatePayout $payout, PayoutStatus $newStatus): void
    {
        if ($newStatus->equals(CompletedPayout::class)) {
            $payout->conversions()->update([
                'status' => PaidConversion::value(),
                'paid_at' => CarbonImmutable::now(),
            ]);

            return;
        }

        if ($newStatus->equals(FailedPayout::class) || $newStatus->equals(CancelledPayout::class)) {
            if ($this->releaseReservedFunds($payout)) {
                return;
            }

            $payout->conversions()->update([
                'status' => ApprovedConversion::value(),
                'affiliate_payout_id' => null,
            ]);
        }
    }

    public function releaseReservedFunds(AffiliatePayout $payout): bool
    {
        return DB::transaction(function () use ($payout): bool {
            $locked = AffiliatePayout::query()->with('operation')->lockForUpdate()->find($payout->id);
            $operation = $locked?->operation;

            if (! $locked instanceof AffiliatePayout
                || ! $operation instanceof AffiliatePayoutOperation
                || $operation->payout_sequence === null
                || $operation->funds_released_at !== null) {
                return false;
            }

            $balance = AffiliateBalance::query()
                ->where('affiliate_id', $operation->affiliate_id)
                ->where('currency', mb_strtoupper((string) $operation->currency))
                ->lockForUpdate()
                ->first();

            if (! $balance instanceof AffiliateBalance) {
                return false;
            }

            $balance->increment('available_minor', $operation->amount_minor);
            $locked->conversions()->update([
                'status' => ApprovedConversion::value(),
                'affiliate_payout_id' => null,
            ]);
            $operation->forceFill(['funds_released_at' => CarbonImmutable::now()])->save();

            return true;
        }, attempts: 3);
    }

    /** @return Collection<int, AffiliatePayout> */
    public function getPayoutsNeedingReconciliation(): Collection
    {
        return AffiliatePayout::query()
            ->whereIn('status', [ProcessingPayout::value(), PendingPayout::value()])
            ->where(static function ($query): void {
                $query->whereHas('operation', static function ($operationQuery): void {
                    $operationQuery->whereIn('status', ['submitting', 'submitted', 'unknown']);
                })->orWhereNotNull('external_reference');
            })
            ->where('updated_at', '<=', CarbonImmutable::now()->subMinutes(5))
            ->get();
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

    /**
     * Settlement summary over an explicit payout set: per-currency legs
     * plus one labeled converted total with rate provenance.
     *
     * @param  Collection<int, AffiliatePayout>  $payouts
     */
    public function summarizePayouts(Collection $payouts, ?string $startDate = null, ?string $endDate = null): array
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
    public function auditAllAffiliateBalances(Affiliate $affiliate): array
    {
        $currencies = $affiliate->balances()->pluck('currency')->unique()->values()->all();

        $audits = [];

        foreach ($currencies as $currency) {
            $audits[(string) $currency] = $this->auditAffiliateBalance($affiliate, (string) $currency);
        }

        return $audits;
    }

    /** @return array<string, int|string|bool> */
    public function auditAffiliateBalance(Affiliate $affiliate, string $currency): array
    {
        $currency = mb_strtoupper($currency);
        $approvedCommissions = (int) $affiliate->conversions()->where('status', ApprovedConversion::value())->where('commission_currency', $currency)->sum('commission_minor');
        $paidOut = (int) $affiliate->payouts()->where('status', CompletedPayout::value())->where('currency', $currency)->sum('total_minor');
        $pendingPayouts = (int) $affiliate->payouts()->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])->where('currency', $currency)->sum('total_minor');
        $expectedAvailable = $approvedCommissions - $paidOut - $pendingPayouts;
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
            'paid_out_minor' => $paidOut,
            'pending_payouts_minor' => $pendingPayouts,
        ];
    }

    private function mapExternalStatus(string $status): ?string
    {
        return match (mb_strtolower($status)) {
            'completed', 'paid', 'success', 'succeeded' => CompletedPayout::class,
            'failed', 'declined', 'rejected', 'error' => FailedPayout::class,
            'pending', 'created' => PendingPayout::class,
            'processing', 'in_progress' => ProcessingPayout::class,
            'cancelled', 'canceled' => CancelledPayout::class,
            default => null,
        };
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
