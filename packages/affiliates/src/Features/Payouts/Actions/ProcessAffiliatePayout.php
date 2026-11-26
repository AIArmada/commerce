<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Contracts\PayoutProcessorRegistry;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliation;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\Queries\PayoutOperationQuery;
use AIArmada\Affiliates\Features\Payouts\Queries\PayoutRequestQuery;
use AIArmada\Affiliates\Features\Payouts\States\CompletedPayout;
use AIArmada\Affiliates\Features\Payouts\States\FailedPayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LogicException;
use Throwable;

final readonly class ProcessAffiliatePayout
{
    public function __construct(
        private PayoutProcessorRegistry $factory,
        private UpdatePayoutStatus $updatePayoutStatus,
        private AssertPayoutCompletable $completionGate,
    ) {}

    public function handle(AffiliatePayout $payout): PayoutResult
    {
        if ((new AffiliatePayoutEvent)->getConnection() !== $payout->getConnection()
            || (new AffiliatePayoutMethod)->getConnection() !== $payout->getConnection()
            || (new Affiliate)->getConnection() !== $payout->getConnection()
            || (new AffiliatePayoutOperation)->getConnection() !== $payout->getConnection()) {
            throw new LogicException('Payout processing requires a shared database connection.');
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $payout->getKey());
        }

        try {
            $connection = $payout->getConnection();
            $claim = $connection->transaction(function () use ($payout): array {
                $lockedQuery = AffiliatePayout::query();

                if ((bool) config('affiliates.owner.enabled', false)) {
                    $lockedQuery->forOwner();
                }

                $locked = $lockedQuery->lockForUpdate()->find($payout->getKey());

                if (! $locked instanceof AffiliatePayout) {
                    return ['error' => PayoutResult::failure('The payout operation is missing.', 'MISSING_PAYOUT_OPERATION')];
                }

                $operation = PayoutOperationQuery::forPayout($locked)->lockForUpdate()->first();

                if (! $operation instanceof AffiliatePayoutOperation) {
                    return ['error' => PayoutResult::failure('The payout operation is missing.', 'MISSING_PAYOUT_OPERATION')];
                }

                if ($locked->status->equals(CompletedPayout::class)) {
                    return ['error' => PayoutResult::success((string) ($locked->external_reference ?? $operation->provider_reference ?? $locked->id))];
                }

                if ($locked->status->equals(FailedPayout::class)) {
                    return ['error' => PayoutResult::failure('The payout has already failed.', 'PAYOUT_ALREADY_FAILED')];
                }

                $affiliate = $locked->payee;

                if (! $affiliate instanceof Affiliate) {
                    return ['error' => PayoutResult::failure('The payout affiliate is missing.', 'MISSING_PAYOUT_AFFILIATE')];
                }

                if ($operation->affiliate_id !== (string) $affiliate->getKey()
                    || $operation->affiliate_payout_id !== (string) $locked->getKey()) {
                    return ['error' => PayoutResult::failure('The payout payee or operation does not match the reservation.', 'PAYOUT_RESERVATION_MISMATCH')];
                }

                $needsReconciliation = $locked->status->equals(ProcessingPayout::class)
                    || in_array($operation->status, ['submitting', 'unknown', 'submitted'], true);

                if ($needsReconciliation && $operation->lease_expires_at?->isFuture()) {
                    return ['error' => PayoutResult::unknown('PAYOUT_LEASE_ACTIVE')];
                }

                // Destination updates and provider claims serialize on the affiliate row.
                Affiliate::query()->whereKey($affiliate->getKey())->lockForUpdate()->firstOrFail();
                $method = AffiliatePayoutMethod::query()->where('affiliate_id', $affiliate->getKey())
                    ->where('is_default', true)->orderBy('id')->lockForUpdate()->first();

                if ($method === null) {
                    if ($needsReconciliation) {
                        $operation->refresh();
                        $wasUnknown = $operation->status === 'unknown'
                            && $operation->last_error_code === 'NO_DEFAULT_PAYOUT_METHOD';
                        $operation->forceFill([
                            'status' => 'unknown',
                            'last_error_code' => 'NO_DEFAULT_PAYOUT_METHOD',
                            'lease_expires_at' => null,
                        ])->save();

                        if (! $wasUnknown) {
                            AffiliatePayoutEvent::query()->create([
                                'affiliate_payout_id' => $locked->getKey(),
                                'from_status' => $locked->status->getValue(),
                                'to_status' => $locked->status->getValue(),
                                'notes' => 'Provider reconciliation required, but no default payout method is configured.',
                            ]);
                        }

                        return ['error' => PayoutResult::unknown('NO_DEFAULT_PAYOUT_METHOD')];
                    }

                    $locked = $this->updatePayoutStatus->handle(
                        $locked,
                        FailedPayout::value(),
                        'No default payout method is configured.',
                    );
                    $operation->refresh();
                    $operation->forceFill(['status' => 'failed', 'last_error_code' => 'NO_DEFAULT_PAYOUT_METHOD'])->save();

                    return ['error' => PayoutResult::failure('No default payout method is configured.', 'NO_DEFAULT_PAYOUT_METHOD')];
                }

                if (! $locked->status->equals(PendingPayout::class) && ! $needsReconciliation) {
                    return ['error' => PayoutResult::failure('The payout is not processable.', 'PAYOUT_NOT_PROCESSABLE')];
                }

                $locked = $this->updatePayoutStatus->handle(
                    $locked,
                    ProcessingPayout::value(),
                    'Payout claimed for processing.',
                );
                $operation->refresh();
                $operation->forceFill([
                    'status' => in_array($operation->status, ['submitting', 'unknown', 'submitted'], true) ? $operation->status : 'submitting',
                    'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
                ])->save();
                $leaseExpiresAt = $operation->fresh()?->lease_expires_at?->toISOString();

                return [
                    'payout' => $locked->fresh(['payee']),
                    'processor_type' => $method->type->value,
                    'request' => PayoutRequestQuery::fromPayout($locked, $method->details),
                    'reconcile' => $needsReconciliation,
                    'lease_expires_at' => $leaseExpiresAt,
                ];
            }, attempts: 3);

            if (isset($claim['error'])) {
                return $claim['error'];
            }

            /** @var AffiliatePayout $claimedPayout */
            $claimedPayout = $claim['payout'];
            $processor = $this->factory->make($claim['processor_type']);

            if ($claim['reconcile']) {
                $providerStatus = $processor->getStatus($claim['request']);
                $status = $providerStatus->status;

                if ($status === 'completed') {
                    return $this->recordResult($claimedPayout, PayoutResult::success((string) ($providerStatus->externalReference ?? $claimedPayout->external_reference ?? $claim['request']->externalReference ?? $claimedPayout->id)), leaseExpiresAt: $claim['lease_expires_at']);
                }

                if (in_array($status, ['failed', 'cancelled'], true)) {
                    return $this->recordResult($claimedPayout, PayoutResult::failure('The provider reported a failed payout.', 'PROVIDER_RECONCILED_FAILURE'), leaseExpiresAt: $claim['lease_expires_at']);
                }

                if ($status !== 'not_found') {
                    return $this->recordResult($claimedPayout, PayoutResult::unknown('PROVIDER_RECONCILIATION_REQUIRED'), leaseExpiresAt: $claim['lease_expires_at']);
                }
            }

            try {
                $claimedPayout->assertHomogeneousConversions();
                $this->completionGate->handle($claimedPayout, false);
            } catch (InvalidArgumentException $exception) {
                throw new PayoutCompletionBlockedException($exception->getMessage(), previous: $exception);
            }

            return $this->recordResult($claimedPayout, $processor->process($claim['request']), leaseExpiresAt: $claim['lease_expires_at']);
        } catch (Throwable $throwable) {
            report($throwable);

            // Completion-gate refusals (non-payable affiliate, drifted
            // conversions) still park as unknown for operator retry, but
            // the gate's reason rides the timeline instead of a generic
            // exception code. Processor and programming errors keep the
            // generic code: only the gate's own exception narrows here.
            if ($throwable instanceof PayoutCompletionBlockedException) {
                return $this->recordResult(
                    $claim['payout'] ?? $payout,
                    PayoutResult::unknown(PayoutReconciliation::COMPLETION_BLOCKED_CODE),
                    'Completion blocked: ' . $throwable->getMessage(),
                    $claim['lease_expires_at'] ?? null,
                );
            }

            return $this->recordResult(
                $claim['payout'] ?? $payout,
                PayoutResult::unknown('PAYOUT_PROCESSING_EXCEPTION'),
                leaseExpiresAt: $claim['lease_expires_at'] ?? null,
            );
        }
    }

    private function recordResult(AffiliatePayout $payout, PayoutResult $result, ?string $notes = null, ?string $leaseExpiresAt = null): PayoutResult
    {
        $connection = $payout->getConnection();
        $connection->transaction(function () use ($payout, $result, $notes, $leaseExpiresAt): bool {
            $lockedQuery = AffiliatePayout::query();

            if ((bool) config('affiliates.owner.enabled', false)) {
                $lockedQuery->forOwner();
            }

            $locked = $lockedQuery->lockForUpdate()->find($payout->getKey());

            if (! $locked instanceof AffiliatePayout) {
                return false;
            }

            $operation = PayoutOperationQuery::forPayout($locked)->lockForUpdate()->first();

            if (! $operation instanceof AffiliatePayoutOperation) {
                return false;
            }

            if ($locked->status->equals(CompletedPayout::class) || $locked->status->equals(FailedPayout::class)) {
                return false;
            }

            if ($leaseExpiresAt === null || $operation->lease_expires_at?->toISOString() !== $leaseExpiresAt) {
                return false;
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
                $operation->refresh();
                $locked->forceFill([
                    'external_reference' => $reference,
                    'metadata' => array_merge($locked->metadata ?? [], $metadata),
                ])->save();
                $operation->forceFill([
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
                $operation->forceFill([
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
                $operation->forceFill([
                    'status' => 'unknown',
                    'provider_reference' => $reference ?? $operation->provider_reference,
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
                $operation->refresh();
                $operation->forceFill([
                    'status' => 'failed',
                    'last_error_code' => $result->failureCode,
                    'lease_expires_at' => null,
                    'completed_at' => CarbonImmutable::now(),
                ])->save();
                $eventRecorded = true;
            }

            if (! $eventRecorded) {
                $toStatus = $locked->fresh()->status->getValue();
                AffiliatePayoutEvent::query()->create([
                    'affiliate_payout_id' => $locked->getKey(),
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'notes' => $notes ?? 'Provider outcome: ' . $result->getStatus() . ($result->failureCode !== null ? ' (' . $result->failureCode . ')' : ''),
                ]);
            }

            return true;
        }, attempts: 3);

        return $result;
    }
}
