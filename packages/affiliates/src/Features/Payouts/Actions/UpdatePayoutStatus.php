<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSettlement;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutRelease;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Webhooks\Api\WebhookApi;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Update the status of an affiliate payout.
 */
final readonly class UpdatePayoutStatus
{
    public function __construct(
        private WebhookApi $webhooks,
        private PayoutConversionSettlement $settlement,
        private PayoutRelease $release,
        private AssertPayoutCompletable $completionGate,
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
        array $metadata = [],
        bool $retainNote = false,
    ): AffiliatePayout {
        if ((new AffiliatePayoutEvent)->getConnection() !== $payout->getConnection()) {
            throw new LogicException('Payout status updates require a shared database connection.');
        }

        return $payout->getConnection()->transaction(function () use ($payout, $status, $notes, $metadata, $retainNote): AffiliatePayout {
            $locked = AffiliatePayout::query()->with('operation')->lockForUpdate()->findOrFail($payout->getKey());

            $from = $locked->status;
            $newStatus = PayoutStatus::fromString($status, $locked);

            if ($from->equals($newStatus::class)) {
                return $locked->refresh();
            }

            $overrideReason = null;

            if ($newStatus->equals(CompletedPayout::class)) {
                // Mirror payout reconciliation: the gate checks WHO
                // may be paid, this checks the linked legs are sane.
                $locked->assertHomogeneousConversions();
                $overrideReason = $this->completionGate->handle($locked);
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

            if ($retainNote && $notes !== null) {
                $locked->metadata = array_merge($locked->metadata ?? [], ['notes' => $notes]);
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
            OwnerContext::withOwner($owner, function () use ($payout): void {
                $this->settlement->complete((string) $payout->getKey());
                $this->syncOperation($payout, 'completed', CarbonImmutable::now());
            });

            return;
        }

        if ($newStatus->equals(FailedPayout::class) || $newStatus->equals(CancelledPayout::class)) {
            OwnerContext::withOwner($owner, function () use ($payout, $newStatus): void {
                $this->release->release((string) $payout->getKey());
                $this->syncOperation($payout, $newStatus->equals(FailedPayout::class) ? 'failed' : 'cancelled', CarbonImmutable::now());
            });
        }
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
