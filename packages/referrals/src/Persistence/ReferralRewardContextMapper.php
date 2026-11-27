<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Persistence;

use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Referrals\Data\ReferralRewardContext;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Models\ReferralReward;

final readonly class ReferralRewardContextMapper
{
    public static function map(ReferralReward $reward, ?string $qualificationBusinessKey, ?string $manualReference = null): ReferralRewardContext
    {
        $program = OwnerScopeOverride::withoutIncludeGlobal(
            static fn (): ReferralProgram => ReferralProgram::query()->whereKey($reward->program_id)->firstOrFail(),
        );

        $ownerTypeColumn = (string) config('referrals.owner.owner_type_column', 'owner_type');
        $ownerIdColumn = (string) config('referrals.owner.owner_id_column', 'owner_id');
        $ownerType = $program->getAttribute($ownerTypeColumn);
        $ownerId = $program->getAttribute($ownerIdColumn);

        return new ReferralRewardContext(
            id: $reward->id,
            inviteId: $reward->invite_id,
            programId: $reward->program_id,
            side: $reward->side->value,
            beneficiaryType: $reward->beneficiary_type,
            beneficiaryId: $reward->beneficiary_id,
            driver: $reward->driver,
            amountMinor: $reward->amount_minor,
            points: $reward->points,
            currency: $reward->currency,
            spec: $reward->spec,
            idempotencyKey: $reward->idempotency_key,
            externalReference: $reward->external_reference,
            metadata: $reward->metadata ?? [],
            qualificationBusinessKey: $qualificationBusinessKey,
            connectionName: $reward->getConnection()->getName(),
            ownerType: is_string($ownerType) && $ownerType !== '' ? $ownerType : null,
            ownerId: $ownerId === null || $ownerId === '' ? null : (string) $ownerId,
            manualReference: $manualReference,
        );
    }
}
