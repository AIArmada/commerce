<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Update the status of an affiliate payout.
 */
final class UpdatePayoutStatus
{
    use AsAction;

    public function __construct(
        private readonly WebhookDispatcher $webhooks,
        private readonly PayoutReconciliationService $reconciliation,
    ) {}

    /**
     * Update the status of a payout.
     *
     * Only transitions declared on the payout state machine are allowed.
     * Completing a payout marks its conversions paid; failing or cancelling
     * releases the reserved funds back to the affiliate balance and returns
     * the conversions to approved so they can be paid again.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        AffiliatePayout $payout,
        string $status,
        ?string $notes = null,
        array $metadata = []
    ): AffiliatePayout {
        return DB::transaction(function () use ($payout, $status, $notes, $metadata): AffiliatePayout {
            $locked = AffiliatePayout::query()->with('operation')->lockForUpdate()->findOrFail($payout->getKey());

            $from = $locked->status;
            $newStatus = PayoutStatus::fromString($status, $locked);

            if ($from->equals($newStatus::class)) {
                return $locked->refresh();
            }

            $overrideReason = null;

            if ($newStatus->equals(CompletedPayout::class)) {
                // Mirror PayoutReconciliationService: the gate checks WHO
                // may be paid, this checks the linked legs are sane.
                $locked->assertHomogeneousConversions();
                $overrideReason = AssertPayoutCompletable::run($locked);
            }

            $locked->status->transitionTo($newStatus::class);

            if ($newStatus->equals(CompletedPayout::class) && $locked->paid_at === null) {
                $locked->paid_at = CarbonImmutable::now();
            }

            if ($newStatus->equals(FailedPayout::class) && $locked->failed_at === null) {
                $locked->failed_at = CarbonImmutable::now();
            }

            if ($newStatus->equals(CancelledPayout::class) && $locked->cancelled_at === null) {
                $locked->cancelled_at = CarbonImmutable::now();
            }

            $locked->save();

            $this->syncConversions($locked, $newStatus);

            AffiliatePayoutEvent::create([
                'affiliate_payout_id' => $locked->getKey(),
                'from_status' => $from?->getValue(),
                'to_status' => $newStatus->getValue(),
                'metadata' => $metadata ?: null,
                'notes' => $notes ?? ($overrideReason !== null ? 'Completed under payout override: ' . $overrideReason : null),
            ]);

            $fresh = $locked->refresh();

            $this->webhooks->dispatch('payout', [
                'id' => $fresh->getKey(),
                'reference' => $fresh->reference,
                'status' => $fresh->status->getValue(),
                'total_minor' => $fresh->total_minor,
                'currency' => $fresh->currency,
            ]);

            return $fresh;
        }, attempts: 3);
    }

    private function syncConversions(AffiliatePayout $payout, PayoutStatus $newStatus): void
    {
        // Same contract as AssertPayoutCompletable: conversion and
        // operation reads/writes evaluate in the payout's own owner
        // scope, never the ambient one, so a mismatched ambient owner
        // cannot turn the count check into a silent 0 === 0 pass — and
        // the operation save (model write, owner-guarded) cannot trip
        // a cross-owner refusal for the payout's own rows.
        $owner = $payout->owner;

        if ($newStatus->equals(CompletedPayout::class)) {
            OwnerContext::withOwner($owner, function () use ($payout, $owner): void {
                $this->syncCompletedConversions($payout, $owner);
            });

            return;
        }

        if ($newStatus->equals(FailedPayout::class) || $newStatus->equals(CancelledPayout::class)) {
            OwnerContext::withOwner($owner, function () use ($payout, $owner, $newStatus): void {
                $this->syncReleasedConversions($payout, $owner, $newStatus);
            });
        }
    }

    private function syncCompletedConversions(AffiliatePayout $payout, mixed $owner): void
    {
        // Approved-only plus a count check: a conversion that left
        // Approved out of band (raced reversal) fails the completion
        // instead of being silently rewritten to paid.
        $expected = $payout->conversions()->forOwner($owner)->count();

        $affected = $payout->conversions()->forOwner($owner)
            ->where('status', ApprovedConversion::value())
            ->update([
                'status' => PaidConversion::value(),
                'paid_at' => CarbonImmutable::now(),
            ]);

        if ($affected !== $expected) {
            throw new PayoutCompletionBlockedException(sprintf(
                'Payout [%s] cannot complete: %d of %d linked conversions left Approved out of band.',
                (string) $payout->getKey(),
                $expected - $affected,
                $expected,
            ));
        }

        $this->syncOperation($payout, 'completed', CarbonImmutable::now());
    }

    private function syncReleasedConversions(AffiliatePayout $payout, mixed $owner, PayoutStatus $newStatus): void
    {
        if ($this->reconciliation->releaseReservedFunds($payout)) {
            $this->syncOperation($payout, $newStatus->equals(FailedPayout::class) ? 'failed' : 'cancelled', CarbonImmutable::now());

            return;
        }

        $payout->conversions()->forOwner($owner)->update([
            'status' => ApprovedConversion::value(),
            'affiliate_payout_id' => null,
        ]);
    }

    private function syncOperation(AffiliatePayout $payout, string $status, CarbonImmutable $at): void
    {
        if ($payout->affiliate_payout_operation_id === null) {
            return;
        }

        $operation = AffiliatePayoutOperation::query()
            ->forOwner($payout->owner)
            ->whereKey($payout->affiliate_payout_operation_id)
            ->first();

        if ($operation === null) {
            return;
        }

        $operation->forceFill([
            'status' => $status,
            'completed_at' => $at,
            'lease_expires_at' => null,
        ])->save();
    }
}
