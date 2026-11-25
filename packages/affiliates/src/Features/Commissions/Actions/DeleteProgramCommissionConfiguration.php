<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Actions;

use AIArmada\Affiliates\Features\Commissions\Api\ProgramCommissionCleanup;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use RuntimeException;

final class DeleteProgramCommissionConfiguration implements ProgramCommissionCleanup
{
    public function deleteForProgram(string $programId): void
    {
        $connection = (new AffiliateProgram)->getConnection();

        foreach ([new AffiliateCommissionRule, new AffiliateCommissionPromotion, new AffiliateVolumeTier] as $model) {
            if ($model->getConnection() !== $connection) {
                throw new RuntimeException('Program commission cleanup requires a shared database connection.');
            }
        }

        $connection->transaction(function () use ($programId): void {
            if ((bool) config('affiliates.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId);
            }

            AffiliateProgram::query()->lockForUpdate()->findOrFail($programId);
            AffiliateCommissionRule::query()->withoutGlobalScope('program_owner')->where('program_id', $programId)->delete();
            AffiliateCommissionPromotion::query()->withoutGlobalScope('program_owner')->where('program_id', $programId)->delete();
            AffiliateVolumeTier::query()->withoutGlobalScope('program_owner')->where('program_id', $programId)->delete();
        }, attempts: 3);
    }
}
