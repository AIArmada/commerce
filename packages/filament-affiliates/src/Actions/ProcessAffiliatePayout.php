<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

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
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessAffiliatePayout
{
    public function __construct(
        private readonly PayoutProcessorFactory $factory,
        private readonly PayoutReconciliationService $reconciliation,
    ) {}

    public function handle(AffiliatePayout $payout): PayoutResult
    {
        try {
            $claim = DB::transaction(function () use ($payout): array {
                $locked = AffiliatePayout::query()->with('operation')->lockForUpdate()->find($payout->id);

                if (! $locked instanceof AffiliatePayout) {
                    return ['error' => PayoutResult::failure('The payout operation is missing.', 'MISSING_PAYOUT_OPERATION')];
                }

                if (! $locked->operation instanceof AffiliatePayoutOperation) {
                    $affiliate = $locked->affiliate;

                    if (! $affiliate instanceof Affiliate) {
                        return ['error' => PayoutResult::failure('The payout affiliate is missing.', 'MISSING_PAYOUT_AFFILIATE')];
                    }

                    $operation = AffiliatePayoutOperation::query()->create([
                        'affiliate_id' => $affiliate->id,
                        'affiliate_payout_id' => $locked->id,
                        'operation_key' => 'legacy:' . $locked->id,
                        'status' => 'claimed',
                        'amount_minor' => $locked->total_minor,
                        'currency' => mb_strtoupper($locked->currency),
                        'claimed_at' => now(),
                        'owner_type' => $locked->owner_type ?? $affiliate->owner_type,
                        'owner_id' => $locked->owner_id ?? $affiliate->owner_id,
                    ]);

                    $locked->forceFill(['affiliate_payout_operation_id' => $operation->id])->save();
                    $locked->setRelation('operation', $operation);
                }

                if ($locked->status->equals(CompletedPayout::class)) {
                    return ['error' => PayoutResult::success((string) ($locked->external_reference ?? $locked->operation->provider_reference ?? $locked->id))];
                }

                if ($locked->status->equals(FailedPayout::class)) {
                    return ['error' => PayoutResult::failure('The payout has already failed.', 'PAYOUT_ALREADY_FAILED')];
                }

                $method = $locked->affiliate?->payoutMethods()->where('is_default', true)->first();

                if ($method === null) {
                    $locked->forceFill(['status' => FailedPayout::class, 'failed_at' => now()])->save();
                    $locked->operation->forceFill(['status' => 'failed', 'last_error_code' => 'NO_DEFAULT_PAYOUT_METHOD'])->save();
                    $locked->events()->create([
                        'from_status' => PendingPayout::value(),
                        'to_status' => FailedPayout::value(),
                        'notes' => 'No default payout method is configured.',
                    ]);

                    return ['error' => PayoutResult::failure('No default payout method is configured.', 'NO_DEFAULT_PAYOUT_METHOD'), 'release' => true];
                }

                $needsReconciliation = $locked->status->equals(ProcessingPayout::class)
                    || in_array($locked->operation->status, ['submitting', 'unknown', 'submitted'], true);

                if (! $locked->status->equals(PendingPayout::class) && ! $needsReconciliation) {
                    return ['error' => PayoutResult::failure('The payout is not processable.', 'PAYOUT_NOT_PROCESSABLE')];
                }

                $locked->forceFill(['status' => ProcessingPayout::class])->save();
                $locked->operation->forceFill([
                    'status' => $needsReconciliation ? $locked->operation->status : 'submitting',
                    'lease_expires_at' => now()->addMinutes(5),
                ])->save();

                return [
                    'payout' => $locked->fresh(['operation', 'affiliate']),
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
            $locked = AffiliatePayout::query()->with('operation')->lockForUpdate()->find($payout->id);

            if (! $locked instanceof AffiliatePayout || ! $locked->operation instanceof AffiliatePayoutOperation) {
                return;
            }

            $fromStatus = $locked->status->getValue();
            $reference = $result->externalReference;
            $metadata = array_filter([
                'provider' => $result->metadata['provider'] ?? null,
                'provider_status' => $result->getStatus(),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            if ($result->getStatus() === 'completed') {
                $locked->forceFill([
                    'status' => CompletedPayout::class,
                    'external_reference' => $reference,
                    'paid_at' => now(),
                    'metadata' => array_merge($locked->metadata ?? [], $metadata),
                ])->save();
                $locked->operation->forceFill([
                    'status' => 'completed',
                    'provider_reference' => $reference,
                    'last_error_code' => null,
                    'completed_at' => now(),
                    'lease_expires_at' => null,
                ])->save();
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
                $locked->forceFill(['status' => FailedPayout::class, 'failed_at' => now()])->save();
                $locked->operation->forceFill([
                    'status' => 'failed',
                    'last_error_code' => $result->failureCode,
                    'lease_expires_at' => null,
                    'completed_at' => now(),
                ])->save();
            }

            $toStatus = $locked->fresh()->status->getValue();
            $locked->events()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'notes' => 'Provider outcome: ' . $result->getStatus() . ($result->failureCode !== null ? ' (' . $result->failureCode . ')' : ''),
            ]);
        }, attempts: 3);

        if ($result->getStatus() === 'failed') {
            $this->reconciliation->releaseReservedFunds($payout);
        }

        return $result;
    }
}
