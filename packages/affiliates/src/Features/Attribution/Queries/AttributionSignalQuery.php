<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Queries;

use AIArmada\Affiliates\Data\AffiliateEventReference;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionSignalReader;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionSignalRecord;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use Illuminate\Support\Str;

final readonly class AttributionSignalQuery implements AttributionSignalReader
{
    public function find(AffiliateEventReference $reference): ?AttributionSignalRecord
    {
        if (! Str::isUuid($reference->id)) {
            return null;
        }

        $row = AffiliateAttribution::query()->withoutOwnerScope()->find($reference->id);

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

        return new AttributionSignalRecord(
            reference: $canonical,
            subjectKey: $row->subject_key ?? $row->cart_identifier,
            subjectInstance: $row->subject_instance ?? $row->cart_instance,
            cartIdentifier: $row->cart_identifier,
            cartInstance: $row->cart_instance,
            cookieValue: $row->cookie_value,
            voucherCode: $row->voucher_code,
            userId: $row->user_id,
            landingUrl: $row->landing_url,
            referrerUrl: $row->referrer_url,
            source: $row->source,
            medium: $row->medium,
            campaign: $row->campaign,
            content: $row->content,
            term: $row->term,
            occurredAt: ($row->last_seen_at ?? $row->created_at)?->toIso8601String(),
        );
    }
}
