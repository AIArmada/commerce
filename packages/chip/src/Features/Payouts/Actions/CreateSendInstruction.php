<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Payouts\Actions;

use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Features\Payouts\Models\BankAccount;
use AIArmada\Chip\Features\Payouts\Models\SendInstruction;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\CommerceSupport\Support\OwnerContext;

final readonly class CreateSendInstruction
{
    public function __construct(private ChipSendService $send) {}

    public function handle(string $amount, int $bankAccountId, string $description, string $reference, string $email): SendInstruction
    {
        $minor = $this->minorUnits($amount);
        $owner = null;

        if ((bool) config('chip.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
        }

        $account = BankAccount::query()
            ->forOwner(OwnerContext::CURRENT, false)
            ->whereKey($bankAccountId)
            ->where('status', 'verified')
            ->where('is_crediting_account', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $account instanceof BankAccount) {
            throw new ChipValidationException('Selected bank account must be verified, active and owned by the current owner.');
        }

        $remote = $this->send->createSendInstruction($minor, $bankAccountId, $description, $reference, $email);
        $instruction = new SendInstruction($remote->toArray());

        if ($owner !== null) {
            $instruction->assignOwner($owner);
        }

        $instruction->save();

        return $instruction;
    }

    private function minorUnits(string $amount): int
    {
        if (preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount) !== 1) {
            throw new ChipValidationException('Payout amount must have no more than two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $digits = mb_ltrim($whole . mb_str_pad($fraction, 2, '0'), '0');
        $max = (string) PHP_INT_MAX;

        if ($digits === '' || mb_strlen($digits) > mb_strlen($max) || (mb_strlen($digits) === mb_strlen($max) && strcmp($digits, $max) > 0)) {
            throw new ChipValidationException('Payout amount is outside the supported range.');
        }

        return (int) $digits;
    }
}
