<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reverse a conversion: refunds, chargebacks, clawbacks.
 *
 * Money moves on the original's transition (holding/available void, or
 * clawback past zero for paid conversions). The compensating leg
 * carries the negated commission as an audit record only — creation
 * accounting ignores Reversed rows, so nothing double-counts.
 *
 * One reversal per conversion: re-entry returns the existing
 * companion leg without moving money again.
 */
final class ReverseAffiliateConversion
{
    use AsAction;

    public function __construct(
        private readonly ApplyConversionAccounting $accounting,
    ) {}

    public function handle(AffiliateConversion $conversion, string $reason): AffiliateConversion
    {
        return DB::transaction(function () use ($conversion, $reason): AffiliateConversion {
            $locked = AffiliateConversion::query()
                ->whereKey($conversion->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->assertNotReservedByOpenPayout();

            if ($locked->status->equals(ReversedConversion::class)) {
                return $this->findCompanionLeg($locked) ?? $locked->refresh();
            }

            $previousStatus = $locked->status;

            if ((int) $locked->commission_minor === 0) {
                // No companion leg is needed, but the transition
                // accounting still runs: a hold recorded before the
                // commission was zeroed must be released, not stranded.
                $locked->update(['status' => ReversedConversion::class]);
                $this->accounting->handle($locked, $previousStatus);

                return $locked->refresh();
            }

            $reversal = $this->findOrCreateLeg($locked, $reason);

            $locked->update(['status' => ReversedConversion::class]);

            $this->accounting->handle($locked, $previousStatus);

            return $reversal;
        }, attempts: 3);
    }

    private function findOrCreateLeg(AffiliateConversion $conversion, string $reason): AffiliateConversion
    {
        $idempotencyKey = hash('sha256', implode('|', [
            'reversal',
            (string) $conversion->getKey(),
            $reason,
        ]));

        $existing = AffiliateConversion::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing instanceof AffiliateConversion) {
            return $existing;
        }

        return AffiliateConversion::create([
            'idempotency_key' => $idempotencyKey,
            'affiliate_id' => $conversion->affiliate_id,
            'affiliate_code' => $conversion->affiliate_code,
            'source_ref' => $conversion->source_ref,
            'external_reference' => $conversion->external_reference,
            'conversion_type' => 'reversal',
            'subtotal_minor' => 0,
            'value_minor' => 0,
            'commission_minor' => -1 * (int) $conversion->commission_minor,
            'commission_currency' => $conversion->commission_currency,
            'status' => ReversedConversion::class,
            'reversed_at' => CarbonImmutable::now(),
            'origin' => $conversion->origin,
            'metadata' => array_merge($conversion->metadata ?? [], [
                'reverses_id' => (string) $conversion->getKey(),
                'reversal_reason' => $reason,
            ]),
            'owner_type' => $conversion->owner_type,
            'owner_id' => $conversion->owner_id,
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }

    private function findCompanionLeg(AffiliateConversion $conversion): ?AffiliateConversion
    {
        return AffiliateConversion::query()
            ->where('conversion_type', 'reversal')
            ->where('metadata->reverses_id', (string) $conversion->getKey())
            ->first();
    }
}
