<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
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
                'notes' => $notes,
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
        if ($newStatus->equals(CompletedPayout::class)) {
            $payout->conversions()->update([
                'status' => PaidConversion::value(),
                'paid_at' => CarbonImmutable::now(),
            ]);

            $this->syncOperation($payout, 'completed', CarbonImmutable::now());

            return;
        }

        if ($newStatus->equals(FailedPayout::class) || $newStatus->equals(CancelledPayout::class)) {
            if ($this->reconciliation->releaseReservedFunds($payout)) {
                $this->syncOperation($payout, $newStatus->equals(FailedPayout::class) ? 'failed' : 'cancelled', CarbonImmutable::now());

                return;
            }

            $payout->conversions()->update([
                'status' => ApprovedConversion::value(),
                'affiliate_payout_id' => null,
            ]);
        }
    }

    private function syncOperation(AffiliatePayout $payout, string $status, CarbonImmutable $at): void
    {
        $operation = $payout->operation;

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
