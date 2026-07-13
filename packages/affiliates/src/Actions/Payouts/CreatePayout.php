<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\Affiliates\States\PendingPayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new payout from affiliate conversions.
 */
final class CreatePayout
{
    use AsAction;

    /**
     * Create a payout from the given conversion IDs.
     *
     * @param  array<int, string>  $conversionIds
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $conversionIds, array $attributes = []): AffiliatePayout
    {
        return DB::transaction(function () use ($conversionIds, $attributes): AffiliatePayout {
            /** @var Collection<int, AffiliateConversion> $conversions */
            $conversions = AffiliateConversion::query()
                ->forOwner()
                ->whereIn('id', $conversionIds)
                ->whereNull('affiliate_payout_id')
                ->get();

            if ($conversions->isEmpty()) {
                throw new InvalidArgumentException('At least one unpaid conversion is required to create a payout.');
            }

            $affiliateIds = $conversions->pluck('affiliate_id')->unique()->values();

            if ($affiliateIds->count() !== 1) {
                throw new InvalidArgumentException('A payout operation may contain conversions for only one affiliate.');
            }

            $total = (int) $conversions->sum('commission_minor');

            if ($total <= 0) {
                throw new InvalidArgumentException('Payout total must be greater than zero.');
            }

            $currency = mb_strtoupper((string) ($attributes['currency'] ?? $conversions->first()?->commission_currency ?? config('affiliates.payouts.currency', 'USD')));
            $reference = $attributes['reference'] ?? $this->generateReference();

            // Handle status - accept either enum or string
            $status = $attributes['status'] ?? PendingPayout::class;
            $status = PayoutStatus::fromString($status);

            $ownerType = $attributes['owner_type'] ?? $conversions->first()?->owner_type;
            $ownerId = $attributes['owner_id'] ?? $conversions->first()?->owner_id;

            $operation = AffiliatePayoutOperation::query()->create([
                'affiliate_id' => (string) $affiliateIds->first(),
                'operation_key' => 'manual:' . (string) Str::uuid(),
                'status' => 'claimed',
                'amount_minor' => $total,
                'currency' => $currency,
                'claimed_at' => now(),
                'lease_expires_at' => now()->addMinutes(5),
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
            ]);

            $payout = AffiliatePayout::create([
                'affiliate_payout_operation_id' => $operation->id,
                'reference' => $reference,
                'status' => $status::class,
                'total_minor' => $total,
                'conversion_count' => $conversions->count(),
                'currency' => $currency,
                'metadata' => $attributes['metadata'] ?? null,
                'payee_type' => $attributes['payee_type'] ?? null,
                'payee_id' => $attributes['payee_id'] ?? null,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'scheduled_at' => $attributes['scheduled_at'] ?? null,
                'paid_at' => $attributes['paid_at'] ?? null,
            ]);

            AffiliateConversion::query()
                ->forOwner()
                ->whereIn('id', $conversions->pluck('id')->all())
                ->whereNull('affiliate_payout_id')
                ->update(['affiliate_payout_id' => $payout->getKey()]);

            $operation->forceFill([
                'affiliate_payout_id' => $payout->id,
                'status' => 'reserved',
            ])->save();

            return $payout;
        });
    }

    private function generateReference(): string
    {
        $prefix = (string) config('affiliates.payouts.reference_prefix', 'PO-');

        return $prefix . Str::upper(Str::random(10));
    }
}
