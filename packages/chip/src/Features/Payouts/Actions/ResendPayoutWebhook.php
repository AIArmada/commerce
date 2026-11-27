<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Payouts\Actions;

use AIArmada\Chip\Features\Payouts\Models\SendInstruction;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ResendPayoutWebhook
{
    public function __construct(private ChipSendService $send) {}

    public function handle(int $instructionId): void
    {
        if (! SendInstruction::query()->forOwner(OwnerContext::CURRENT, false)->whereKey($instructionId)->exists()) {
            throw new AuthorizationException('Payout is unavailable for this owner.');
        }

        $this->send->resendSendInstructionWebhook($instructionId);
    }
}
