<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Actions;

use AIArmada\Rewards\Enums\RewardPointLotStatus;
use AIArmada\Rewards\Models\RewardPointLot;
use Carbon\CarbonImmutable;

final readonly class ExpireDueRewardPointLotsAction
{
    public function __construct(private ExpireRewardPointsAction $expire) {}

    /**
     * @return array{movements: int, scopes: int}
     */
    public function handle(CarbonImmutable $at): array
    {
        $scopes = RewardPointLot::query()
            ->where('status', RewardPointLotStatus::Open)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $at)
            ->whereNull('expired_at')
            ->distinct()
            ->get(['recipient_type', 'recipient_id', 'family_key', 'unit']);

        $movements = 0;

        foreach ($scopes as $scope) {
            $movements += count($this->expire->handle(
                (string) $scope->recipient_type,
                (string) $scope->recipient_id,
                (string) $scope->family_key,
                (string) $scope->unit,
                $at,
                'lot-expiry',
                'system',
                null,
            ));
        }


        return ['movements' => $movements, 'scopes' => $scopes->count()];
    }
}
