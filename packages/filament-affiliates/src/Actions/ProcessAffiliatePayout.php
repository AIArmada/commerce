<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Actions\Payouts\UpdatePayoutStatus;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessAffiliatePayout
{
    public function __construct(
        private readonly PayoutProcessorFactory $factory,
        private readonly PayoutReconciliationService $reconciliation,
        private readonly UpdatePayoutStatus $updatePayoutStatus,
    ) {}

    public function handle(AffiliatePayout $payout): PayoutResult
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $payout->getKey());
        }

        try {
            $claim = DB::transaction(function () use ($payout): array {
                $lockedQuery = AffiliatePayout::query()->with('operation');

                if ((bool) config('affiliates.owner.enabled', false)) {
                    $lockedQuery->forOwner();
                }

                $locked = $lockedQuery->lockForUpdate()->find($payout->getKey());

                if (! $locked instanceof AffiliatePayout) {
                    return ['error' => PayoutResult::failure('The payout operation is missing.', 'MISSING_PAYOUT_OPERATION')];
                }

                if (! $locked->operation instanceof AffiliatePayoutOperation) {
                    return ['error' => PayoutResult::failure('The payout operation is missing.', 'MISSING_PAYOUT_OPERATION')];
                }

                if ($locked->status->equals(CompletedPayout::class)) {
                    return ['error' => PayoutResult::success((string) ($locked->external_reference ?? $locked->operation->provider_reference ?? $locked->id))];
                }

                if ($locked->status->equals(FailedPayout::class)) {
                    return ['error' => PayoutResult::failure('The payout has already failed.', 'PAYOUT_ALREADY_FAILED')];
                }

                $affiliate = $locked->payee;

                if (! $affiliate instanceof Affiliate) {
                    return ['error' => PayoutResult::failure('The payout affiliate is missing.', 'MISSING_PAYOUT_AFFILIATE')];
                }

                $method = $affiliate->payoutMethods()->where('is_default', true)->first();

                if ($method === null) {
                    $locked = $this->updatePayoutStatus->handle(
                        $locked,
                        FailedPayout::value(),
                        'No default payout method is configured.',
                    );
                    $locked->load('operation');
                    $locked->operation->forceFill(['status' => 'failed', 'last_error_code' => 'NO_DEFAULT_PAYOUT_METHOD'])->save();

                    return ['error' => PayoutResult::failure('No default payout method is configured.', 'NO_DEFAULT_PAYOUT_METHOD'), 'release' => true];
                }

                $needsReconciliation = $locked->status->equals(ProcessingPayout::class)
                    || in_array($locked->operation->status, ['submitting', 'unknown', 'submitted'], true);

                if (! $locked->status->equals(PendingPayout::class) && ! $needsReconciliation) {
                    return ['error' => PayoutResult::failure('The payout is not processable.', 'PAYOUT_NOT_PROCESSABLE')];
                }

                $locked = $this->updatePayoutStatus->handle(
                    $locked,
                    ProcessingPayout::value(),
                    'Payout claimed for processing.',
                );
                $locked->load('operation');
                $locked->operation->forceFill([
                    'status' => $needsReconciliation ? $locked->operation->status : 'submitting',
                    'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
                ])->save();

                return [
                    'payout' => $locked->fresh(['operation', 'payee']),
                    'processor_type' => $method->type->value,
                    'reconcile' => $needsReconciliation,
                ];
            }, attempts: 3);

            if (isset($claim['error'])) {
                if (($claim['release'] ?? false) === true) {
                    $this->reconciliation->releaseReservedFunds($payout);
                }

                return $claim['error'];
            }

            /** @var AffiliatePayout $claimedPayout */
            $claimedPayout = $claim['payout'];
            $processor = $this->factory->make($claim['processor_type']);

            if ($claim['reconcile']) {
                $status = $processor->getStatus($claimedPayout);

                if ($status === 'completed') {
                    return $this->recordResult($claimedPayout, PayoutResult::success((string) ($claimedPayout->external_reference ?? $claimedPayout->operation?->provider_reference ?? $claimedPayout->id)));
                }

                if (in_array($status, ['failed', 'cancelled'], true)) {
                    return $this->recordResult($claimedPayout, PayoutResult::failure('The provider reported a failed payout.', 'PROVIDER_RECONCILED_FAILURE'));
                }

                if ($status !== 'not_found') {
                    return $this->recordResult($claimedPayout, PayoutResult::unknown('PROVIDER_RECONCILIATION_REQUIRED'));
                }
            }

            return $this->recordResult($claimedPayout, $processor->process($claimedPayout));
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->recordResult($payout, PayoutResult::unknown('PAYOUT_PROCESSING_EXCEPTION'));
        }
    }

    private function recordResult(AffiliatePayout $payout, PayoutResult $result): PayoutResult
    {
        DB::transaction(function () use ($payout, $result): void {
            $lockedQuery = AffiliatePayout::query()->with('operation');

            if ((bool) config('affiliates.owner.enabled', false)) {
                $lockedQuery->forOwner();
            }

            $locked = $lockedQuery->lockForUpdate()->find($payout->getKey());

            if (! $locked instanceof AffiliatePayout || ! $locked->operation instanceof AffiliatePayoutOperation) {
                return;
            }

            $fromStatus = $locked->status->getValue();
            $reference = $result->externalReference;
            $metadata = array_filter([
                'provider' => $result->metadata['provider'] ?? null,
                'provider_status' => $result->getStatus(),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            $eventRecorded = false;

            if ($result->getStatus() === 'completed') {
                $locked = $this->updatePayoutStatus->handle(
                    $locked,
                    CompletedPayout::value(),
                    'Provider outcome: completed',
                    $metadata,
                );
                $locked->load('operation');
                $locked->forceFill([
                    'external_reference' => $reference,
                    'metadata' => array_merge($locked->metadata ?? [], $metadata),
                ])->save();
                $locked->operation->forceFill([
                    'status' => 'completed',
                    'provider_reference' => $reference,
                    'last_error_code' => null,
                    'completed_at' => CarbonImmutable::now(),
                    'lease_expires_at' => null,
                ])->save();
                $eventRecorded = true;
            } elseif ($result->isPending()) {
                $locked->forceFill([
                    'status' => ProcessingPayout::class,
                    'external_reference' => $reference,
                    'metadata' => array_merge($locked->metadata ?? [], $metadata),
                ])->save();
                $locked->operation->forceFill([
                    'status' => 'submitted',
                    'provider_reference' => $reference,
                    'last_error_code' => null,
                    'lease_expires_at' => null,
                ])->save();
            } elseif ($result->isUnknown()) {
                $locked->forceFill([
                    'status' => ProcessingPayout::class,
                    'external_reference' => $reference ?? $locked->external_reference,
                    'metadata' => array_merge($locked->metadata ?? [], ['provider_status' => 'unknown']),
                ])->save();
                $locked->operation->forceFill([
                    'status' => 'unknown',
                    'provider_reference' => $reference ?? $locked->operation->provider_reference,
                    'last_error_code' => $result->failureCode,
                    'lease_expires_at' => null,
                ])->save();
            } else {
                $locked = $this->updatePayoutStatus->handle(
                    $locked,
                    FailedPayout::value(),
                    'Provider outcome: failed' . ($result->failureCode !== null ? ' (' . $result->failureCode . ')' : ''),
                    $metadata,
                );
                $locked->load('operation');
                $locked->operation->forceFill([
                    'status' => 'failed',
                    'last_error_code' => $result->failureCode,
                    'lease_expires_at' => null,
                    'completed_at' => CarbonImmutable::now(),
                ])->save();
                $eventRecorded = true;
            }

            if (! $eventRecorded) {
                $toStatus = $locked->fresh()->status->getValue();
                $locked->events()->create([
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'notes' => 'Provider outcome: ' . $result->getStatus() . ($result->failureCode !== null ? ' (' . $result->failureCode . ')' : ''),
                ]);
            }
        }, attempts: 3);

        if ($result->getStatus() === 'failed') {
            $this->reconciliation->releaseReservedFunds($payout);
        }

        return $result;
    }
}
