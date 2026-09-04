<?php

declare(strict_types=1);

namespace AIArmada\Chip\Facades;

use AIArmada\Chip\Services\ChipSendService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, mixed> listAccounts()
 * @method static \AIArmada\Chip\Data\SendInstructionData createSendInstruction(int $amountInCents, int $recipientBankAccountId, string $description, string $reference, string $email, bool $sendRecipientReceipt = false)
 * @method static \AIArmada\Chip\Data\SendInstructionData getSendInstruction(int $id)
 * @method static array<string, mixed> listSendInstructions(array<string, mixed> $filters = [])
 * @method static \AIArmada\Chip\Data\SendLimitData getSendLimit(int $id)
 * @method static \AIArmada\Chip\Data\SendLimitData increaseBudgetAllocation(int|float $amount)
 * @method static array<string, mixed> listSendLimits(array<string, mixed> $filters = [])
 * @method static array<string, mixed> resendApprovalRequest(int $id)
 * @method static \AIArmada\Chip\Data\BankAccountData createBankAccount(string $bankCode, string $accountNumber, string $accountHolderName, string $reference)
 * @method static \AIArmada\Chip\Data\BankAccountData getBankAccount(int $id)
 * @method static array<string, mixed> listBankAccounts(array<string, mixed> $filters = [])
 * @method static void deleteBankAccount(int $id)
 * @method static array<string, mixed> resendBankAccountWebhook(int $id)
 * @method static void deleteSendInstruction(int $id)
 * @method static array<string, mixed> resendSendInstructionWebhook(int $id)
 * @method static array<string, mixed> createGroup(array<string, mixed> $data)
 * @method static array<string, mixed> getGroup(int $id)
 * @method static array<string, mixed> updateGroup(int $id, array<string, mixed> $data)
 * @method static void deleteGroup(int $id)
 * @method static array<string, mixed> listGroups(array<string, mixed> $filters = [])
 * @method static \AIArmada\Chip\Data\SendWebhookData createSendWebhook(array<string, mixed> $data)
 * @method static \AIArmada\Chip\Data\SendWebhookData getSendWebhook(int $id)
 * @method static \AIArmada\Chip\Data\SendWebhookData updateSendWebhook(int $id, array<string, mixed> $data)
 * @method static void deleteSendWebhook(int $id)
 * @method static array{results: array<int, \AIArmada\Chip\Data\SendWebhookData>, meta?: array<string, mixed>} listSendWebhooks(array<string, mixed> $filters = [])
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
