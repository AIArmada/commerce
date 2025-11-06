<?php

declare(strict_types=1);

namespace AIArmada\Chip\Facades;

use AIArmada\Chip\Services\ChipSendService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, mixed> listAccounts()
 * @method static \AIArmada\Chip\DataObjects\SendInstruction createSendInstruction(int $amountInCents, string $currency, string $recipientBankAccountId, string $description, string $reference, string $email)
 * @method static \AIArmada\Chip\DataObjects\SendInstruction getSendInstruction(string $id)
 * @method static array<string, mixed> listSendInstructions(array<string, mixed> $filters = [])
 * @method static \AIArmada\Chip\DataObjects\SendLimit getSendLimit(int|string $id)
 * @method static \AIArmada\Chip\DataObjects\BankAccount createBankAccount(string $bankCode, string $accountNumber, string $accountHolderName, ?string $reference = null)
 * @method static \AIArmada\Chip\DataObjects\BankAccount getBankAccount(string $id)
 * @method static array<string, mixed> listBankAccounts(array<string, mixed> $filters = [])
 * @method static \AIArmada\Chip\DataObjects\BankAccount updateBankAccount(string $id, array<string, mixed> $data)
 * @method static void deleteBankAccount(string $id)
 * @method static void resendBankAccountWebhook(string $id)
 * @method static \AIArmada\Chip\DataObjects\SendInstruction cancelSendInstruction(string $id)
 * @method static void deleteSendInstruction(string $id)
 * @method static void resendSendInstructionWebhook(string $id)
 * @method static array<string, mixed> createGroup(array<string, mixed> $data)
 * @method static array<string, mixed> getGroup(string $id)
 * @method static array<string, mixed> updateGroup(string $id, array<string, mixed> $data)
 * @method static void deleteGroup(string $id)
 * @method static array<string, mixed> listGroups(array<string, mixed> $filters = [])
 * @method static \AIArmada\Chip\DataObjects\SendWebhook createSendWebhook(array<string, mixed> $data)
 * @method static \AIArmada\Chip\DataObjects\SendWebhook getSendWebhook(string $id)
 * @method static \AIArmada\Chip\DataObjects\SendWebhook updateSendWebhook(string $id, array<string, mixed> $data)
 * @method static void deleteSendWebhook(string $id)
 * @method static array<int, \AIArmada\Chip\DataObjects\SendWebhook>|array{data: array<int, \AIArmada\Chip\DataObjects\SendWebhook>, meta?: array<string, mixed>} listSendWebhooks(array<string, mixed> $filters = [])
 *
 * @see ChipSendService
 */
final class ChipSend extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChipSendService::class;
    }
}
