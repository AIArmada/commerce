<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Tests\Ledgers\Support;

use AIArmada\Rewards\Contracts\RewardPayoutExecution;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Data\RewardPayoutInstruction;
use OutOfBoundsException;

/**
 * Scripted payout executor for core tests: replays queued executions in
 * order so every outcome mapping is provable without a provider.
 */
final class ScriptedPayoutExecutor implements RewardPayoutExecutor
{
    /**
     * @param  list<RewardPayoutExecution>  $executions
     */
    public function __construct(
        private array $executions = [],
        private bool $cancelConfirmed = true,
        private string $providerStatus = 'submitted',
    ) {}

    /** @var list<array{0: string, 1: string}> */
    public array $settled = [];

    public function submit(RewardPayoutInstruction $request): RewardPayoutExecution
    {
        if ($this->executions === []) {
            throw new OutOfBoundsException('No scripted payout execution remains.');
        }

        return array_shift($this->executions);
    }

    public function cancel(RewardPayoutInstruction $request): bool
    {
        return $this->cancelConfirmed;
    }

    public function status(RewardPayoutInstruction $request): string
    {
        return $this->providerStatus;
    }

    public function settle(RewardPayoutInstruction $request, string $externalStatus, array $context = []): void
    {
        $this->settled[] = [(string) $request->id, $externalStatus];
    }
}
