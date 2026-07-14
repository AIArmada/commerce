<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Record an attributed non-cart conversion.
 *
 * Cart conversions use RecordAffiliateConversion, while referral, signup, and
 * engagement flows need the same conversion ledger without a cart aggregate.
 */
final class RecordAffiliateOutcome
{
    use AsAction;

    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        AffiliateAttribution $attribution,
        string $conversionType,
        string $externalReference,
        array $payload = [],
    ): ?AffiliateConversionData {
        $affiliate = $attribution->relationLoaded('affiliate')
            ? $attribution->affiliate
            : $attribution->affiliate()->first();

        if ($affiliate === null) {
            return null;
        }

        $existing = AffiliateConversion::query()
            ->where('external_reference', $externalReference)
            ->first();

        if ($existing instanceof AffiliateConversion) {
            return AffiliateConversionData::fromModel($existing);
        }

        $metadata = Arr::get($payload, 'metadata', []);

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $conversion = AffiliateConversion::query()->create([
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_attribution_id' => $attribution->getKey(),
            'affiliate_code' => $affiliate->code,
            'subject_type' => Arr::get($payload, 'subject_type', $attribution->subject_type),
            'subject_identifier' => Arr::get($payload, 'subject_identifier', $attribution->subject_identifier),
            'subject_instance' => Arr::get($payload, 'subject_instance', $attribution->subject_instance),
            'subject_title_snapshot' => Arr::get($payload, 'subject_title_snapshot', $attribution->subject_title_snapshot),
            'external_reference' => $externalReference,
            'conversion_type' => $conversionType,
            'subtotal_minor' => (int) Arr::get($payload, 'subtotal_minor', 0),
            'value_minor' => (int) Arr::get($payload, 'value_minor', 0),
            'commission_minor' => (int) Arr::get($payload, 'commission_minor', 0),
            'commission_currency' => (string) Arr::get($payload, 'commission_currency', config('affiliates.currency.default', 'USD')),
            'status' => Arr::get($payload, 'status', config('affiliates.commissions.default_status', 'pending')),
            'channel' => Arr::get($payload, 'channel'),
            'metadata' => $metadata,
            'occurred_at' => Arr::get($payload, 'occurred_at', now()),
            'approved_at' => Arr::get($payload, 'approved_at'),
        ]);

        $conversionData = AffiliateConversionData::fromModel($conversion);

        if (Arr::get($payload, 'dispatch_event', true)) {
            $this->events->dispatch(new AffiliateConversionRecorded($conversionData));
        }

        return $conversionData;
    }
}
