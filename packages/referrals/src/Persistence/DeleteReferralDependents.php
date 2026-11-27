<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Persistence;

use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Models\ReferralReward;
use LogicException;

final class DeleteReferralDependents
{
    public function forProgram(ReferralProgram $program): void
    {
        $this->assertSharedConnection($program);

        ReferralReward::query()
            ->where('program_id', $program->getKey())
            ->delete();

        ReferralInvite::query()
            ->where('program_id', $program->getKey())
            ->delete();
    }

    public function forInvite(ReferralInvite $invite): void
    {
        $this->assertSharedConnection($invite);

        ReferralReward::query()
            ->where('invite_id', $invite->getKey())
            ->delete();
    }

    private function assertSharedConnection(ReferralProgram | ReferralInvite $model): void
    {
        $connection = $model->getConnection();

        foreach ([new ReferralProgram, new ReferralInvite, new ReferralReward] as $related) {
            if ($related->getConnection() !== $connection) {
                throw new LogicException('Referral dependents require a shared database connection.');
            }
        }
    }
}
