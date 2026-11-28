<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Persistence;

use AIArmada\Rewards\Features\Finance\Models\RewardPayoutRequest;
use AIArmada\Rewards\Support\RewardProgramGuard;
use InvalidArgumentException;

final class RewardPayoutGuard
{
    public static function findOrFail(RewardPayoutRequest | string $request): RewardPayoutRequest
    {
        $id = $request instanceof RewardPayoutRequest ? (string) $request->getKey() : $request;
        $resolved = RewardPayoutRequest::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardPayoutRequest) {
            throw new InvalidArgumentException('Unknown reward payout request.');
        }

        RewardProgramGuard::findProgramOrFail($resolved->program_id);

        return $resolved;
    }
}
