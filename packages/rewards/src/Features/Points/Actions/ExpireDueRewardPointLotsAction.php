<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Points\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Rewards\Enums\RewardPointLotStatus;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardLockApi;
use AIArmada\Rewards\Features\Points\Models\RewardPointLot;
use AIArmada\Rewards\Features\Points\Persistence\RewardPointLotScope;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ExpireDueRewardPointLotsAction
{
    public function __construct(private ExpireRewardPointsAction $expire) {}

    /** @return array{movements: int, scopes: int} */
    public function handle(CarbonImmutable $at): array
    {
        $system = ! OwnerContext::hasOverride() && OwnerContext::resolve() === null;
        $movements = 0;
        $scopes = [];

        $lots = RewardPointLot::query()
            ->where('status', RewardPointLotStatus::Open)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $at)
            ->whereNull('expired_at')
            ->lazyById(200);

        foreach ($lots as $lot) {
            if (! $system) {
                try {
                    RewardPointLotScope::assertAccessible([$lot]);
                } catch (AuthorizationException) {
                    continue;
                }
            }

            $expire = fn (): int => count($this->expire->handle(
                $lot->recipient_type,
                $lot->recipient_id,
                $lot->family_key,
                $lot->unit,
                $at,
                lotId: (string) $lot->getKey(),
            ));

            $movements += $system ? $this->underLotOwner($lot, $expire) : $expire();
            $scopes[serialize([$lot->recipient_type, $lot->recipient_id, $lot->family_key, $lot->unit])] = true;
        }

        return ['movements' => $movements, 'scopes' => count($scopes)];
    }

    /** @param callable(): int $callback */
    private function underLotOwner(RewardPointLot $lot, callable $callback): int
    {
        $underProgram = static fn (): mixed => $lot->program_id === null
            ? $callback()
            : app(RewardProgramLockApi::class)->withProgramOwnerWhenExists($lot->program_id, $callback);

        return (int) OwnerContext::withOwner(null, static fn (): mixed => $lot->award_id === null
            ? $underProgram()
            : app(RewardAwardLockApi::class)->withAwardOwnerWhenExists($lot->award_id, $underProgram));
    }
}
