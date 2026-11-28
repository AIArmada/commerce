<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Queries;

use AIArmada\Rewards\Enums\RewardPayeeStatus;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardDecision;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardLockApi;
use AIArmada\Rewards\Features\Finance\Api\RewardPayeeSelectionApi;
use AIArmada\Rewards\Features\Finance\Models\RewardPayee;
use Illuminate\Database\Eloquent\Builder;

final readonly class RewardPayeeSelectionQuery implements RewardPayeeSelectionApi
{
    public function __construct(private RewardAwardLockApi $awards) {}

    public function optionsForAward(string $awardId): array
    {
        $award = $this->awards->findAwardDecision($awardId);

        if ($award === null) {
            return [];
        }

        return $this->verifiedPayees($award)
            ->orderByDesc('verified_at')
            ->orderBy('id')
            ->get(['id', 'method', 'account_display'])
            ->mapWithKeys(static fn (RewardPayee $payee): array => [
                (string) $payee->getKey() => $payee->method . ' — ' . $payee->account_display,
            ])
            ->all();
    }

    public function methodForAward(string $awardId, string $payeeId): ?string
    {
        if ($payeeId === '') {
            return null;
        }

        $award = $this->awards->findAwardDecision($awardId);

        if ($award === null) {
            return null;
        }

        $method = $this->verifiedPayees($award)->whereKey($payeeId)->value('method');

        return is_string($method) ? $method : null;
    }

    /** @return Builder<RewardPayee> */
    private function verifiedPayees(RewardAwardDecision $award): Builder
    {
        return RewardPayee::query()
            ->where('recipient_type', $award->recipientType)
            ->where('recipient_id', $award->recipientId)
            ->where('status', RewardPayeeStatus::Verified->value);
    }
}
