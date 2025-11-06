<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\DataObjects\BankAccount;
use AIArmada\Chip\DataObjects\SendInstruction;
use AIArmada\Chip\DataObjects\SendLimit;
use AIArmada\Chip\DataObjects\SendWebhook;

class ChipSendService
{
    public function __construct(
        private ChipSendClient $client
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listAccounts(): array
    {
        return $this->client->get('send/accounts');
    }

    public function createSendInstruction(
        int $amountInCents,
        string $currency,
        string $recipientBankAccountId,
        string $description,
        string $reference,
        string $email
    ): SendInstruction {
        $data = [
            'bank_account_id' => $recipientBankAccountId,
            'amount' => number_format($amountInCents / 100, 2, '.', ''),
            'currency' => $currency,
            'description' => $description,
            'reference' => $reference,
            'email' => $email,
        ];

        $response = $this->client->post('send/send_instructions', $data);

        return SendInstruction::fromArray($response);
    }

    public function getSendInstruction(string $id): SendInstruction
    {
        $response = $this->client->get("send/send_instructions/{$id}");

        return SendInstruction::fromArray($response);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listSendInstructions(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'send/send_instructions'.($queryString ? "?{$queryString}" : '');

        return $this->client->get($endpoint);
    }

    public function getSendLimit(int|string $id): SendLimit
    {
        $response = $this->client->get("send/send_limits/{$id}");

        return SendLimit::fromArray($response);
    }

    public function createBankAccount(
        string $bankCode,
        string $accountNumber,
        string $accountHolderName,
        ?string $reference = null
    ): BankAccount {
        $data = [
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
            'name' => $accountHolderName,
        ];

        if ($reference) {
            $data['reference'] = $reference;
        }

        $response = $this->client->post('send/bank_accounts', $data);

        return BankAccount::fromArray($response);
    }

    public function getBankAccount(string $id): BankAccount
    {
        $response = $this->client->get("send/bank_accounts/{$id}");

        return BankAccount::fromArray($response);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listBankAccounts(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'send/bank_accounts'.($queryString ? "?{$queryString}" : '');

        return $this->client->get($endpoint);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateBankAccount(string $id, array $data): BankAccount
    {
        $response = $this->client->put("send/bank_accounts/{$id}", $data);

        return BankAccount::fromArray($response);
    }

    public function deleteBankAccount(string $id): void
    {
        $this->client->delete("send/bank_accounts/{$id}");
    }

    public function cancelSendInstruction(string $id): SendInstruction
    {
        $response = $this->client->post("send/send_instructions/{$id}/cancel");

        return SendInstruction::fromArray($response['data'] ?? $response);
    }

    /**
     * Create a group
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createGroup(array $data): array
    {
        return $this->client->post('send/groups', $data);
    }

    /**
     * Get a group
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $id): array
    {
        return $this->client->get("send/groups/{$id}");
    }

    /**
     * Update a group
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateGroup(string $id, array $data): array
    {
        return $this->client->put("send/groups/{$id}", $data);
    }

    /**
     * Delete a group
     */
    public function deleteGroup(string $id): void
    {
        $this->client->delete("send/groups/{$id}");
    }

    /**
     * List groups
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listGroups(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'send/groups'.($queryString ? '?'.$queryString : '');

        return $this->client->get($endpoint);
    }

    /**
     * Create a webhook for CHIP Send
     *
     * @param  array<string, mixed>  $data
     */
    public function createSendWebhook(array $data): SendWebhook
    {
        $response = $this->client->post('send/webhooks', $data);

        return SendWebhook::fromArray($response);
    }

    /**
     * Get a CHIP Send webhook
     */
    public function getSendWebhook(string $id): SendWebhook
    {
        $response = $this->client->get("send/webhooks/{$id}");

        return SendWebhook::fromArray($response);
    }

    /**
     * Update a CHIP Send webhook
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSendWebhook(string $id, array $data): SendWebhook
    {
        $response = $this->client->put("send/webhooks/{$id}", $data);

        return SendWebhook::fromArray($response);
    }

    /**
     * Delete a CHIP Send webhook
     */
    public function deleteSendWebhook(string $id): void
    {
        $this->client->delete("send/webhooks/{$id}");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, SendWebhook>|array{data: array<int, SendWebhook>, meta?: array<string, mixed>}
     */
    public function listSendWebhooks(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'send/webhooks'.($queryString ? '?'.$queryString : '');

        $response = $this->client->get($endpoint);

        if (isset($response['data']) && is_array($response['data'])) {
            $response['data'] = array_map(static fn (array $item) => SendWebhook::fromArray($item), $response['data']);

            return $response;
        }

        if (array_is_list($response)) {
            return array_map(static fn (array $item) => SendWebhook::fromArray($item), $response);
        }

        return [];
    }

    /**
     * Delete a send instruction
     */
    public function deleteSendInstruction(string $id): void
    {
        $this->client->delete("send/send_instructions/{$id}");
    }

    /**
     * Resend a send instruction webhook
     *
     * @return array<string, mixed>
     */
    public function resendSendInstructionWebhook(string $id): array
    {
        return $this->client->post("send/send_instructions/{$id}/resend_webhook");
    }

    /**
     * Resend a bank account webhook
     *
     * @return array<string, mixed>
     */
    public function resendBankAccountWebhook(string $id): array
    {
        return $this->client->post("send/bank_accounts/{$id}/resend_webhook");
    }
}
