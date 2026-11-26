<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleRecord;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReference;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AffiliateLifecycleQuery implements AffiliateLifecycleReader
{
    public function find(AffiliateLifecycleReference $reference): ?AffiliateLifecycleRecord
    {
        if (! Str::isUuid($reference->id)) {
            return null;
        }

        $affiliate = Affiliate::query()->withoutOwnerScope()->find($reference->id);

        return $affiliate === null ? null : $this->project($affiliate, $reference);
    }

    public function findById(string $id): ?AffiliateLifecycleRecord
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $affiliate = Affiliate::query()->withoutOwnerScope()->find($id);

        return $affiliate === null ? null : $this->project($affiliate, $affiliate->lifecycleReference());
    }

    public function project(Affiliate $affiliate, AffiliateLifecycleReference $reference): AffiliateLifecycleRecord
    {
        $canonical = $affiliate->lifecycleReference();

        if ($reference->id !== $canonical->id || $reference->ownerType !== $canonical->ownerType || $reference->ownerId !== $canonical->ownerId) {
            throw new InvalidArgumentException('Trusted affiliate owner or identity does not match the canonical source.');
        }

        return new AffiliateLifecycleRecord(
            reference: $canonical,
            code: $affiliate->code,
            name: $affiliate->name,
            active: $affiliate->isActive(),
            canBeAttributed: $affiliate->canBeAttributed(),
            createdAt: $affiliate->created_at === null ? null : CarbonImmutable::instance($affiliate->created_at),
            registrationApprovalMode: $affiliate->registration_approval_mode,
        );
    }
}
