<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionInspection;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionInspectionReader;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Support\Str;
use LogicException;

final readonly class InspectConversion implements ConversionInspectionReader
{
    public function find(string $conversionId): ?ConversionInspection
    {
        return Str::isUuid($conversionId)
            ? $this->project(AffiliateConversion::query()->forOwner(includeGlobal: true)->find($conversionId))
            : null;
    }

    public function assertConnection(int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliateConversion)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Conversion inspection requires a shared database connection.');
        }
    }

    public function lock(string $conversionId, int $connectionIdentity): ?ConversionInspection
    {
        $this->assertConnection($connectionIdentity);

        if ((new AffiliateConversion)->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Conversion inspection lock requires an active transaction.');
        }

        return Str::isUuid($conversionId)
            ? $this->project(AffiliateConversion::query()->forOwner(includeGlobal: true)->lockForUpdate()->find($conversionId))
            : null;
    }

    private function project(?AffiliateConversion $conversion): ?ConversionInspection
    {
        return $conversion === null ? null : new ConversionInspection(
            id: (string) $conversion->getKey(),
            affiliateId: $conversion->affiliate_id,
            attributionId: $conversion->affiliate_attribution_id,
            ownerType: $conversion->owner_type,
            ownerId: $conversion->owner_id,
            actorUserId: $conversion->actor_user_id,
            status: $conversion->status->getValue(),
            commissionMinor: $conversion->commission_minor,
            occurredAt: $conversion->occurred_at?->toImmutable(),
            connectionIdentity: spl_object_id($conversion->getConnection()),
        );
    }
}
