<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Queries;

use AIArmada\Rewards\Features\Finance\Api\RewardPayoutQueryApi;
use AIArmada\Rewards\Features\Finance\Api\RewardPayoutSnapshot;
use AIArmada\Rewards\Features\Finance\Models\RewardPayoutRequest;

final readonly class RewardPayoutQuery implements RewardPayoutQueryApi
{
    public function forAward(string $awardId, bool $lock = false): ?RewardPayoutSnapshot
    {
        $query = RewardPayoutRequest::query()->where('award_id', $awardId);
        $request = $lock ? $query->lockForUpdate()->first() : $query->first();

        if (! $request instanceof RewardPayoutRequest) {
            return null;
        }

        return new RewardPayoutSnapshot(
            (string) $request->getKey(),
            $request->status->value,
            $request->amount_minor,
            $request->currency,
            $request->confirmed_at !== null && $request->external_payout_id !== null,
        );
    }
}
