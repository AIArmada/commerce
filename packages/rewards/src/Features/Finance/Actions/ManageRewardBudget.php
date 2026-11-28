<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Actions;

use AIArmada\Rewards\Enums\RewardBudgetMovementKind;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardLockApi;
use AIArmada\Rewards\Features\Finance\Api\RewardBudgetApi;
use AIArmada\Rewards\Features\Finance\Models\RewardBudgetMovement;
use AIArmada\Rewards\Features\Finance\Persistence\RewardBudgetLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManageRewardBudget implements RewardBudgetApi
{
    public function reserveObligation(string $programId, string $currency, string $awardId, int $amount, int $epoch, string $actorType, ?string $actorId, string $actorRole, CarbonImmutable $decided): void
    {
        $this->moveObligation(RewardBudgetMovementKind::Reserve, $programId, $currency, $awardId, $amount, $epoch, $actorType, $actorId, $actorRole, $decided);
    }

    public function releaseObligation(string $programId, string $currency, string $awardId, int $amount, int $epoch, string $actorType, ?string $actorId, string $actorRole, CarbonImmutable $decided): void
    {
        $this->moveObligation(RewardBudgetMovementKind::Release, $programId, $currency, $awardId, $amount, $epoch, $actorType, $actorId, $actorRole, $decided);
    }

    private function moveObligation(RewardBudgetMovementKind $kind, string $programId, string $currency, string $awardId, int $amount, int $epoch, string $actorType, ?string $actorId, string $actorRole, CarbonImmutable $decided): void
    {
        $this->assertAwardProgram($awardId, $programId);
        $currency = RewardBudgetLedger::normalizeCurrency($currency);
        $key = $kind === RewardBudgetMovementKind::Reserve
            ? RewardBudgetLedger::obligateKey($awardId, $epoch)
            : RewardBudgetLedger::releaseObligateKey($awardId, $epoch);

        DB::transaction(static function () use ($kind, $key, $programId, $currency, $amount, $awardId, $actorType, $actorId, $actorRole, $decided): void {
            RewardBudgetLedger::lockFunding($programId, $currency);
            RewardBudgetLedger::append($kind, $key, $programId, $currency, $amount, $awardId, $actorType, $actorId, $actorRole, $decided);
        });
    }

    public function recoverConfirmedSpend(string $programId, string $currency, string $awardId, int $amount, string $recoveryKey, string $actorType, ?string $actorId, string $actorRole, CarbonImmutable $decided): void
    {
        $this->assertAwardProgram($awardId, $programId);
        $currency = RewardBudgetLedger::normalizeCurrency($currency);

        DB::transaction(static function () use ($programId, $currency, $awardId, $amount, $recoveryKey, $actorType, $actorId, $actorRole, $decided): void {
            $funded = RewardBudgetLedger::lockFunding($programId, $currency);
            RewardBudgetLedger::assertNonNegativeLegs(RewardBudgetLedger::lockedBalances($programId, $currency, $funded));

            $spent = (int) RewardBudgetMovement::query()
                ->where('program_id', $programId)
                ->where('currency', $currency)
                ->where('award_id', $awardId)
                ->where('kind', RewardBudgetMovementKind::Spend)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('amount_minor')
                ->sum();
            $recovered = (int) RewardBudgetMovement::query()
                ->where('program_id', $programId)
                ->where('currency', $currency)
                ->where('award_id', $awardId)
                ->where('kind', RewardBudgetMovementKind::Recover)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('amount_minor')
                ->sum();

            if ($amount <= 0 || $recovered + $amount > $spent) {
                throw new InvalidArgumentException('Recovery exceeds the award\'s outstanding confirmed spend; refusing to mint budget.');
            }

            RewardBudgetLedger::append(RewardBudgetMovementKind::Recover, "recover:{$recoveryKey}", $programId, $currency, $amount, $awardId, $actorType, $actorId, $actorRole, $decided);
            RewardBudgetLedger::assertNonNegativeLegs(RewardBudgetLedger::lockedBalances($programId, $currency, $funded));
        });
    }

    private function assertAwardProgram(string $awardId, string $programId): void
    {
        app(RewardAwardLockApi::class)->assertAwardProgram($awardId, $programId);
    }
}
