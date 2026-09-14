<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\Commissions\CommissionCaps;
use AIArmada\Affiliates\Services\FraudDetectionService;
use AIArmada\Affiliates\States\RejectedConversion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\QueryException;
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
        private readonly ApplyConversionAccounting $accounting,
        private readonly FraudDetectionService $fraud,
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

        $metadata = Arr::except($metadata, [
            'affiliate_id', 'affiliate_code', 'affiliate_attribution_id',
            'subject_type', 'subject_key', 'subject_id', 'subject_instance',
            'subject_title_snapshot', 'origin', 'voucher_code', 'program_id',
        ]);

        $idempotencyKey = hash('sha256', implode('|', [
            $affiliate->owner_type ?? '',
            $affiliate->owner_id ?? '',
            (string) $affiliate->getKey(),
            $conversionType,
            $externalReference,
        ]));

        $conversion = new AffiliateConversion([
            'idempotency_key' => $idempotencyKey,
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_attribution_id' => $attribution->getKey(),
            'affiliate_program_id' => Arr::get($payload, 'affiliate_program_id', $attribution->affiliate_program_id),
            'affiliate_code' => $affiliate->code,
            'subject_type' => Arr::get($payload, 'subject_type', $attribution->subject_type),
            'subject_key' => Arr::get($payload, 'subject_key', $attribution->subject_key),
            'subject_instance' => Arr::get($payload, 'subject_instance', $attribution->subject_instance),
            'subject_title_snapshot' => Arr::get($payload, 'subject_title_snapshot', $attribution->subject_title_snapshot),
            'subject_id' => Arr::get($payload, 'subject_id', $attribution->subject_id),
            'affiliate_link_id' => Arr::get($payload, 'affiliate_link_id', $attribution->affiliate_link_id),
            'external_reference' => $externalReference,
            'commission_override' => Arr::get($payload, 'commission_override', $attribution->commission_override),
            'upline_levels' => Arr::get($payload, 'upline_levels', $attribution->upline_levels),
            'conversion_type' => $conversionType,
            'subtotal_minor' => (int) Arr::get($payload, 'subtotal_minor', 0),
            'value_minor' => (int) Arr::get($payload, 'value_minor', 0),
            'commission_minor' => CommissionCaps::clamp((int) Arr::get($payload, 'commission_minor', 0)),
            'commission_currency' => (string) Arr::get($payload, 'commission_currency', config('affiliates.currency.default', 'USD')),
            'status' => Arr::get($payload, 'status', config('affiliates.commissions.default_status', 'pending')),
            'channel' => Arr::get($payload, 'channel'),
            'origin' => Arr::get($payload, 'origin', $attribution->origin),
            'sharer_user_id' => Arr::get($payload, 'sharer_user_id', $attribution->sharer_user_id),
            'actor_user_id' => Arr::get($payload, 'actor_user_id'),
            'metadata' => $metadata,
            'occurred_at' => Arr::get($payload, 'occurred_at', CarbonImmutable::now()),
            'approved_at' => Arr::get($payload, 'approved_at'),
        ]);
        $conversion->forceFill([
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
        ]);

        try {
            $conversion->save();
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = AffiliateConversion::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof AffiliateConversion) {
                return AffiliateConversionData::fromModel($existing);
            }

            throw $exception;
        }

        if (! $this->fraud->analyzeConversion($conversion)['allowed']) {
            $conversion->update(['status' => RejectedConversion::class]);
        }

        $this->accounting->handle($conversion);

        $conversionData = AffiliateConversionData::fromModel($conversion);

        if (Arr::get($payload, 'dispatch_event', true)) {
            $this->events->dispatch(new AffiliateConversionRecorded($conversionData));
        }

        return $conversionData;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
