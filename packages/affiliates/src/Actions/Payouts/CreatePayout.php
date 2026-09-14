<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use Carbon\CarbonImmutable;
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
     * Only approved, unlinked conversions may be paid. The payout reserves the
     * affiliate's available balance immediately, mirroring ClaimScheduledPayout,
     * so completing, failing, or cancelling the payout stays consistent.
     *
     * @param  array<int, string>  $conversionIds
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $conversionIds, array $attributes = []): AffiliatePayout
    {
        $conversionIds = array_values(array_unique(array_filter($conversionIds)));

        if ($conversionIds === []) {
            throw new InvalidArgumentException('At least one unpaid conversion is required to create a payout.');
        }

        return DB::transaction(function () use ($conversionIds, $attributes): AffiliatePayout {
            /** @var Collection<int, AffiliateConversion> $conversions */
            $conversions = AffiliateConversion::query()
                ->forOwner()
                ->whereIn('id', $conversionIds)
                ->where('status', ApprovedConversion::value())
                ->whereNull('affiliate_payout_id')
                ->lockForUpdate()
                ->get();

            if ($conversions->count() !== count($conversionIds)) {
                throw new InvalidArgumentException('All conversions must be approved and not already linked to a payout.');
            }

            $affiliateIds = $conversions->pluck('affiliate_id')->unique()->values();

            if ($affiliateIds->count() !== 1) {
                throw new InvalidArgumentException('A payout operation may contain conversions for only one affiliate.');
            }

            $affiliate = Affiliate::query()->forOwner()->lockForUpdate()->findOrFail((string) $affiliateIds->first());

            $ownerTuples = $conversions
                ->map(fn (AffiliateConversion $conversion): string => ($conversion->owner_type ?? '') . '|' . ($conversion->owner_id ?? ''))
                ->unique();

            if ($ownerTuples->count() !== 1) {
                throw new InvalidArgumentException('A payout operation may contain conversions for only one owner.');
            }

            $ownerType = $conversions->first()?->owner_type;
            $ownerId = $conversions->first()?->owner_id;

            $this->assertOwnerAttributesMatch($attributes, $ownerType, $ownerId);

            $total = (int) $conversions->sum('commission_minor');

            if ($total <= 0) {
                throw new InvalidArgumentException('Payout total must be greater than zero.');
            }

            $balance = $this->reserveBalance($affiliate, $total);

            $currency = mb_strtoupper((string) ($attributes['currency'] ?? $conversions->first()?->commission_currency ?? config('affiliates.payouts.currency', 'USD')));
            $reference = $attributes['reference'] ?? $this->generateReference();

            // Handle status - accept either enum or string. Terminal states are
            // rejected: conversions and balance sync happen through status
            // transitions after creation, never at creation time.
            $status = $attributes['status'] ?? PendingPayout::class;
            $status = PayoutStatus::fromString($status);

            if (! $status->equals(PendingPayout::class) && ! $status->equals(ProcessingPayout::class)) {
                throw new InvalidArgumentException('A payout can only be created as pending or processing.');
            }

            $sequence = $balance ? $balance->payout_sequence + 1 : null;

            $operation = new AffiliatePayoutOperation([
                'affiliate_id' => (string) $affiliateIds->first(),
                'operation_key' => $sequence === null
                    ? 'manual:' . (string) Str::uuid()
                    : sprintf('manual:%s:%d', $affiliateIds->first(), $sequence),
                'status' => 'claimed',
                'amount_minor' => $total,
                'currency' => $currency,
                'payout_sequence' => $sequence,
                'claimed_at' => CarbonImmutable::now(),
                'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
            ]);
            $operation->forceFill(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
            $operation->save();

            $payout = new AffiliatePayout([
                'affiliate_payout_operation_id' => $operation->id,
                'reference' => $reference,
                'status' => $status::class,
                'total_minor' => $total,
                'conversion_count' => $conversions->count(),
                'currency' => $currency,
                'metadata' => $attributes['metadata'] ?? null,
                'payee_type' => $attributes['payee_type'] ?? $affiliate->getMorphClass(),
                'payee_id' => $attributes['payee_id'] ?? $affiliate->getKey(),
                'scheduled_at' => $attributes['scheduled_at'] ?? null,
                'paid_at' => $attributes['paid_at'] ?? null,
            ]);
            $payout->forceFill(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
            $payout->save();

            if ($balance && $sequence !== null) {
                $balance->forceFill([
                    'available_minor' => $balance->available_minor - $total,
                    'payout_sequence' => $sequence,
                ])->save();
            }

            $claimed = AffiliateConversion::query()
                ->forOwner()
                ->whereIn('id', $conversions->pluck('id')->all())
                ->where('status', ApprovedConversion::value())
                ->whereNull('affiliate_payout_id')
                ->update(['affiliate_payout_id' => $payout->getKey()]);

            if ($claimed !== $conversions->count()) {
                throw new InvalidArgumentException('All conversions must be approved and not already linked to a payout.');
            }

            $operation->forceFill([
                'affiliate_payout_id' => $payout->id,
                'status' => 'reserved',
            ])->save();

            return $payout;
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertOwnerAttributesMatch(array $attributes, ?string $ownerType, ?string $ownerId): void
    {
        foreach (['owner_type' => $ownerType, 'owner_id' => $ownerId] as $key => $expected) {
            if (! array_key_exists($key, $attributes) || $attributes[$key] === null) {
                continue;
            }

            if ((string) $attributes[$key] !== (string) $expected) {
                throw new InvalidArgumentException('Payout owner must match the conversions being paid.');
            }
        }
    }

    private function reserveBalance(Affiliate $affiliate, int $total): ?AffiliateBalance
    {
        if (! ApplyConversionAccounting::balancesSyncEnabled()) {
            return null;
        }

        $balance = AffiliateBalance::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->lockForUpdate()
            ->first();

        if (! $balance instanceof AffiliateBalance || $balance->available_minor < $total) {
            throw new InvalidArgumentException('Affiliate available balance does not cover the payout total.');
        }

        return $balance;
    }

    private function generateReference(): string
    {
        $prefix = (string) config('affiliates.payouts.reference_prefix', 'PO-');

        return $prefix . Str::upper(Str::random(10));
    }
}
