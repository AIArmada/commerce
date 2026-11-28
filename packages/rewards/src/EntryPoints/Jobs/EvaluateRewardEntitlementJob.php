<?php

declare(strict_types=1);

namespace AIArmada\Rewards\EntryPoints\Jobs;

use AIArmada\Rewards\Features\Entitlements\Actions\EvaluateRewardSourceAction;
use AIArmada\Rewards\Features\Entitlements\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Data\RewardBeneficiaryRole;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class EvaluateRewardEntitlementJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $businessKey,
        public readonly ?string $onlyRecipientId = null,
        public readonly ?RewardBeneficiaryRole $onlyRole = null,
        public readonly ?string $onlyRecipientType = null,
        public readonly ?string $onlyFamilyKey = null,
    ) {}

    public function uniqueId(): string
    {
        return 'reward-eval:' . $this->businessKey . $this->scopeSuffix();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(RewardSourceFactReader $reader, EvaluateRewardEntitlementAction $evaluate): void
    {
        (new EvaluateRewardSourceAction(
            $this->businessKey,
            $this->onlyRecipientId,
            $this->onlyRole,
            $this->onlyRecipientType,
            $this->onlyFamilyKey,
        ))->handle($reader, $evaluate);
    }

    private function scopeSuffix(): string
    {
        if ($this->onlyRecipientId === null && $this->onlyRole === null && $this->onlyRecipientType === null && $this->onlyFamilyKey === null) {
            return '';
        }

        return ':' . implode('|', [
            $this->onlyRecipientType ?? '',
            $this->onlyRecipientId ?? '',
            $this->onlyRole?->value ?? '',
            $this->onlyFamilyKey ?? '',
        ]);
    }
}
