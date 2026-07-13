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
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PayoutReconciliationService
{
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

            $fromStatus = $locked->status->getValue();
            $reference = isset($externalData['reference']) && is_string($externalData['reference'])
                ? mb_trim($externalData['reference'])
                : null;
            $providerStatus = isset($externalData['status']) && is_string($externalData['status'])
                ? mb_substr($externalData['status'], 0, 64)
                : mb_strtolower($externalStatus);
            $newStatus = PayoutStatus::fromString($statusClass, $locked);
            $metadata = array_merge($locked->metadata ?? [], array_filter([
                'reconciled_at' => now()->toIso8601String(),
                'provider_status' => $providerStatus,
                'external_reference' => $reference,
                'external_data' => $externalData === [] ? null : $externalData,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            $locked->forceFill([
                'status' => $statusClass,
                'paid_at' => $newStatus->equals(CompletedPayout::class) ? now() : $locked->paid_at,
                'failed_at' => $newStatus->equals(FailedPayout::class) ? now() : $locked->failed_at,
                'cancelled_at' => $newStatus->equals(CancelledPayout::class) ? now() : $locked->cancelled_at,
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
                    'completed_at' => $newStatus->equals(CompletedPayout::class) || $newStatus->equals(FailedPayout::class) || $newStatus->equals(CancelledPayout::class) ? now() : null,
                    'lease_expires_at' => null,
                ])->save();
            }

            $locked->events()->create([
                'from_status' => $fromStatus,
                'to_status' => $newStatus->getValue(),
                'notes' => 'Provider status reconciled to ' . $newStatus->getValue(),
                'metadata' => ['provider_status' => $providerStatus],
            ]);

            return true;
        }, attempts: 3);

        if ($changed && in_array($statusClass, [FailedPayout::class, CancelledPayout::class], true)) {
            $this->releaseReservedFunds($payout);
        }

        return $changed;
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
            $operation->forceFill(['funds_released_at' => now()])->save();

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
            ->where('updated_at', '<=', now()->subMinutes(5))
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

        $payouts = $query->get();
        $byStatus = $payouts->groupBy(fn (AffiliatePayout $item): string => $item->status->getValue())->map->count();
        $totalAmount = (int) $payouts->sum('total_minor');
        $completedAmount = (int) $payouts->filter(fn (AffiliatePayout $item): bool => $item->status->equals(CompletedPayout::class))->sum('total_minor');
        $failedAmount = (int) $payouts->filter(fn (AffiliatePayout $item): bool => $item->status->equals(FailedPayout::class))->sum('total_minor');

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_payouts' => $payouts->count(),
                'total_amount_minor' => $totalAmount,
                'completed_amount_minor' => $completedAmount,
                'failed_amount_minor' => $failedAmount,
                'pending_amount_minor' => $totalAmount - $completedAmount - $failedAmount,
            ],
            'by_status' => $byStatus->all(),
            'discrepancies' => $this->findDiscrepancies($payouts),
        ];
    }

    /** @return array<string, int|string|bool> */
    public function auditAffiliateBalance(Affiliate $affiliate): array
    {
        $approvedCommissions = (int) $affiliate->conversions()->where('status', ApprovedConversion::value())->sum('commission_minor');
        $paidOut = (int) $affiliate->payouts()->where('status', CompletedPayout::value())->sum('total_minor');
        $pendingPayouts = (int) $affiliate->payouts()->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])->sum('total_minor');
        $expectedAvailable = $approvedCommissions - $paidOut - $pendingPayouts;
        $actualAvailable = $affiliate->balance?->available_minor ?? 0;
        $discrepancy = $expectedAvailable - $actualAvailable;

        return [
            'affiliate_id' => (string) $affiliate->id,
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
