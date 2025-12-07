<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
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
    ) {}

    /**
     * Update the status of a payout.
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
            $from = $payout->status;

            $payout->status = $status;

            if ($status === 'paid' && $payout->paid_at === null) {
                $payout->paid_at = now();
            }

            $payout->save();

            AffiliatePayoutEvent::create([
                'affiliate_payout_id' => $payout->getKey(),
                'from_status' => $from,
                'to_status' => $status,
                'metadata' => $metadata ?: null,
                'notes' => $notes,
            ]);

            $fresh = $payout->refresh();

            $this->webhooks->dispatch('payout', [
                'id' => $fresh->getKey(),
                'reference' => $fresh->reference,
                'status' => $fresh->status,
                'total_minor' => $fresh->total_minor,
                'currency' => $fresh->currency,
            ]);

            return $fresh;
        });
    }
}
