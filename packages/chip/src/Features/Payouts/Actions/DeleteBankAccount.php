<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Payouts\Actions;

use AIArmada\Chip\Features\Payouts\Models\BankAccount;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class DeleteBankAccount
{
    public function __construct(private ChipSendService $send) {}

    public function handle(int $accountId): void
    {
        $account = BankAccount::query()
            ->forOwner(OwnerContext::CURRENT, false)
            ->whereKey($accountId)
            ->whereNull('deleted_at')
            ->first();

        if ($account === null) {
            throw new AuthorizationException('Bank account is unavailable for this owner.');
        }

        $this->send->deleteBankAccount($accountId);

        $account->forceFill(['deleted_at' => CarbonImmutable::now()])->save();
    }
}
