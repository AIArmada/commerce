<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Data\AwardRecipient;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfile;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use LogicException;

final readonly class ReadAffiliateProfiles implements AffiliateProfileReader
{
    public function __construct(private GuardAffiliateAccess $guard) {}

    public function morphType(): string
    {
        return (new Affiliate)->getMorphClass();
    }

    public function matchesMorphType(?string $type): bool
    {
        return $type !== null && is_a(Relation::getMorphedModel($type) ?? $type, Affiliate::class, true);
    }

    public function find(string $id, int $connectionIdentity, bool $includeGlobal = false): ?AffiliateProfile
    {
        $this->guard->assertConnection($connectionIdentity);

        if (! Str::isUuid($id)) {
            return null;
        }

        return $this->project(Affiliate::query()->forOwner(
            includeGlobal: $includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal(),
        )->whereKey($id)->first());
    }

    public function lock(string $id, int $connectionIdentity): ?AffiliateProfile
    {
        $this->guard->assertConnection($connectionIdentity);

        if ((new Affiliate)->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Affiliate profile locks require an active transaction.');
        }

        if (! Str::isUuid($id)) {
            return null;
        }

        return $this->project(Affiliate::query()->forOwner(includeGlobal: false)->whereKey($id)->lockForUpdate()->first());
    }

    private function project(?Affiliate $affiliate): ?AffiliateProfile
    {
        return $affiliate === null ? null : new AffiliateProfile(
            reference: $affiliate->lifecycleReference(),
            code: $affiliate->code,
            name: $affiliate->name,
            handle: $affiliate->handle,
            currency: $affiliate->currency,
            active: $affiliate->isActive(),
            commercial: $affiliate->isCommercialPurpose(),
            canBeAttributed: $affiliate->canBeAttributed(),
            canReceivePayout: $affiliate->canReceivePayout(),
            awardRecipient: new AwardRecipient(
                (string) $affiliate->getKey(),
                $affiliate->getMorphClass(),
                (string) $affiliate->getConnection()->getName(),
                $affiliate->owner_type,
                $affiliate->owner_id === null ? null : (string) $affiliate->owner_id,
            ),
            parentAffiliateId: $affiliate->parent_affiliate_id,
            status: $affiliate->status->getValue(),
            data: $affiliate->toData(),
        );
    }
}
