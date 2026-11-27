<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Support;

use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Models\ReferralReward;
use Illuminate\Database\ConnectionInterface;
use LogicException;

final class ReferralDatabase
{
    public static function connection(): ConnectionInterface
    {
        $connection = (new ReferralProgram)->getConnection();

        foreach ([new ReferralInvite, new ReferralReward] as $model) {
            if ($model->getConnection() !== $connection) {
                throw new LogicException('Referral writes require one shared database connection.');
            }
        }

        return $connection;
    }
}
