<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reverse a conversion: refunds, chargebacks, clawbacks.
 *
 * The original row is marked reversed (never edited otherwise) and a
 * compensating leg carries the negated commission. Readers sum posted
 * legs; reversed originals never pay out.
 */
final class ReverseAffiliateConversion
{
    public function execute(AffiliateConversion $conversion, string $reason): AffiliateConversion
    {
        if ((int) $conversion->commission_minor === 0) {
            $conversion->update(['status' => ReversedConversion::class]);

            return $conversion->refresh();
        }

        $idempotencyKey = hash('sha256', implode('|', [
            'reversal',
            (string) $conversion->getKey(),
            $reason,
        ]));

        $existing = AffiliateConversion::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing instanceof AffiliateConversion) {
            $conversion->update(['status' => ReversedConversion::class]);

            return $existing;
        }

        return DB::transaction(function () use ($conversion, $reason, $idempotencyKey): AffiliateConversion {
            $reversal = AffiliateConversion::create([
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

            $conversion->update(['status' => ReversedConversion::class]);

            return $reversal;
        });
    }
}
