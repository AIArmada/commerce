<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSettlement;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliation;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutRelease;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class ReconcilePayout implements PayoutReconciliation
{
    public function __construct(
        private PayoutConversionSettlement $settlement,
        private PayoutRelease $release,
        private AssertPayoutCompletable $completionGate,
    ) {}

    /** @param array<string, mixed> $externalData */
    public function reconcile(string $payoutId, string $externalStatus, array $externalData = []): bool
    {
        $statusClass = $this->mapExternalStatus($externalStatus);

        if ($statusClass === null) {
            return false;
        }

        $connection = (new AffiliatePayout)->getConnection();

        if ((new AffiliatePayoutOperation)->getConnection() !== $connection || (new AffiliatePayoutEvent)->getConnection() !== $connection) {
            throw new LogicException('Payout reconciliation requires a shared database connection.');
        }

        $changed = $connection->transaction(function () use ($payoutId, $statusClass, $externalData, $externalStatus): bool {
            $locked = AffiliatePayout::query()->forOwner()->with('operation')->lockForUpdate()->find($payoutId);

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

            $overrideReason = null;

            if ($newStatus->equals(CompletedPayout::class)) {
                $locked->assertHomogeneousConversions();
                $overrideReason = $this->completionGate->handle($locked);
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

            AffiliatePayoutEvent::query()->create([
                'affiliate_payout_id' => $locked->getKey(),
                'from_status' => $fromStatus,
                'to_status' => $newStatus->getValue(),
                'notes' => 'Provider status reconciled to ' . $newStatus->getValue() . ($overrideReason !== null ? ' under payout override: ' . $overrideReason : ''),
                'metadata' => ['provider_status' => $providerStatus],
            ]);

            return true;
        }, attempts: 3);

        return $changed;
    }

    private function syncConversions(AffiliatePayout $payout, PayoutStatus $newStatus): void
    {
        OwnerContext::withOwner($payout->owner, function () use ($payout, $newStatus): void {
            if ($newStatus->equals(CompletedPayout::class)) {
                $this->settlement->complete((string) $payout->getKey());
            } elseif ($newStatus->equals(FailedPayout::class) || $newStatus->equals(CancelledPayout::class)) {
                $this->release->release((string) $payout->getKey());
            }
        });
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
}
