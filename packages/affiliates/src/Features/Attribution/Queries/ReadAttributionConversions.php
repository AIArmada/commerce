<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Queries;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionConversionReader;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionConversionRecord;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use LogicException;

final readonly class ReadAttributionConversions implements AttributionConversionReader
{
    public function active(?string $attributionId, ?string $cartIdentifier, string $cartInstance, int $connectionIdentity): ?AttributionConversionRecord
    {
        $this->assertConnection($connectionIdentity);

        if ($attributionId !== null && ! Str::isUuid($attributionId)) {
            return null;
        }

        $query = AffiliateAttribution::query()->active()->forOwner(includeGlobal: true);

        if ($attributionId !== null) {
            $query->whereKey($attributionId);
        } else {
            $query->where('cart_identifier', $cartIdentifier)
                ->where('cart_instance', $cartInstance)
                ->latest('last_seen_at')
                ->latest('id');
        }

        $row = $query->first();

        return $row === null ? null : $this->record($row);
    }

    public function frozen(string $id, int $connectionIdentity): AttributionConversionRecord
    {
        $this->assertConnection($connectionIdentity);
        $this->assertId($id);

        return $this->record(AffiliateAttribution::query()->forOwner()->findOrFail($id));
    }

    public function forOutcome(string $id, int $connectionIdentity): AttributionConversionRecord
    {
        $this->assertConnection($connectionIdentity);
        $this->assertId($id);

        $row = (bool) config('affiliates.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(AffiliateAttribution::class, $id)
            : AffiliateAttribution::query()->findOrFail($id);

        return $this->record($row);
    }

    private function assertId(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(AffiliateAttribution::class, [$id]);
        }
    }

    private function assertConnection(int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliateAttribution)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Attribution recording requires a shared database connection.');
        }
    }

    private function record(AffiliateAttribution $row): AttributionConversionRecord
    {
        return new AttributionConversionRecord(
            (string) $row->getKey(),
            $row->affiliate_id,
            $row->affiliate_code,
            $row->affiliate_link_id,
            $row->affiliate_program_id,
            $row->subject_type,
            $row->subject_key,
            $row->subject_id,
            $row->subject_instance,
            $row->subject_title_snapshot,
            $row->voucher_code,
            $row->commission_override,
            $row->upline_levels,
            $row->channel,
            $row->origin,
            $row->sharer_user_id,
            $row->owner_type,
            $row->owner_id === null ? null : (string) $row->owner_id,
            $row->last_referred_at === null ? null : CarbonImmutable::instance($row->last_referred_at),
        );
    }
}
