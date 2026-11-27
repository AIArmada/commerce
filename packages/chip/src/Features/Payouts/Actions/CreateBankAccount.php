<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Payouts\Actions;

use AIArmada\Chip\Features\Payouts\Models\BankAccount;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\CommerceSupport\Support\OwnerContext;

final readonly class CreateBankAccount
{
    public function __construct(private ChipSendService $send) {}

    public function handle(string $bankCode, string $accountNumber, string $accountHolderName, string $reference): BankAccount
    {
        $owner = null;

        if ((bool) config('chip.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
        }

        $remote = $this->send->createBankAccount($bankCode, $accountNumber, $accountHolderName, $reference);
        $account = new BankAccount;
        $account->fill(array_replace($remote->toArray(), ['reference' => $remote->reference ?? $reference]));

        if ($owner !== null) {
            $account->assignOwner($owner);
        }

        $account->save();

        return $account;
    }
}
