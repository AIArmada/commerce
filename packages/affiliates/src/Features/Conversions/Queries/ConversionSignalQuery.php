<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Data\AffiliateEventReference;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionSignalReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionSignalRecord;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use Illuminate\Support\Str;

final readonly class ConversionSignalQuery implements ConversionSignalReader
{
    public function find(AffiliateEventReference $reference): ?ConversionSignalRecord
    {
        if (! Str::isUuid($reference->id)) {
            return null;
        }

        $row = AffiliateConversion::query()->withoutOwnerScope()->find($reference->id);

        if ($row === null) {
            return null;
        }

        $canonical = new AffiliateEventReference(
            id: (string) $row->getKey(),
            affiliateId: $row->affiliate_id,
            affiliateCode: $row->affiliate_code,
            ownerType: $row->owner_type,
            ownerId: $row->owner_id === null ? null : (string) $row->owner_id,
        );

        if (! $reference->matches($canonical)) {
            return null;
        }

        return new ConversionSignalRecord(
            reference: $canonical,
            attributionId: $row->affiliate_attribution_id,
            subjectKey: $row->subject_key,
            subjectInstance: $row->subject_instance,
            voucherCode: $row->voucher_code,
            externalReference: $row->external_reference,
            conversionType: $row->conversion_type,
            subtotalMinor: $row->subtotal_minor,
            valueMinor: $row->value_minor,
            commissionMinor: $row->commission_minor,
            commissionCurrency: $row->commission_currency,
            status: $row->status->getValue(),
            channel: $row->channel,
            occurredAt: ($row->occurred_at ?? $row->created_at)?->toIso8601String(),
        );
    }
}
