<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\Data\BankAccountData;
use AIArmada\Chip\Data\SendInstructionData;
use AIArmada\Chip\Data\SendLimitData;
use AIArmada\Chip\Data\SendWebhookData;
use AIArmada\Chip\Exceptions\ChipValidationException;

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

    /**
     * Create a send instruction (payout).
     *
     * @throws ChipValidationException If validation fails
     */
    public function createSendInstruction(
        int $amountInCents,
        int $recipientBankAccountId,
        string $description,
        string $reference,
        string $email,
        bool $sendRecipientReceipt = false,
    ): SendInstructionData {
        $this->validateSendInstruction($amountInCents, $email, $description, $reference);

        $data = [
            'bank_account_id' => $recipientBankAccountId,
            'amount' => sprintf('%d.%02d', intdiv($amountInCents, 100), $amountInCents % 100),
            'description' => $description,
            'reference' => $reference,
            'email' => $email,
        ];

        if ($sendRecipientReceipt) {
            $data['send_recipient_receipt'] = true;
        }

        $response = $this->client->post('send/send_instructions', $data);

        return SendInstructionData::from($response);
    }

    public function getSendInstruction(int $id): SendInstructionData
    {
        $response = $this->client->get("send/send_instructions/{$id}");

        return SendInstructionData::from($response);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listSendInstructions(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'send/send_instructions' . ($queryString ? "?{$queryString}" : '');

        return $this->client->get($endpoint);
    }

    public function getSendLimit(int $id): SendLimitData
    {
        $response = $this->client->get("send/send_limits/{$id}");

        return SendLimitData::from($response);
    }

    /**
     * Request an increase to the CHIP Send budget allocation.
     *
     * The CHIP Send API expresses this amount in major currency units (for
     * example, 100 means RM100), not minor units.
     */
    public function increaseBudgetAllocation(int | float $amount): SendLimitData
    {
        if ($amount <= 0) {
            throw new ChipValidationException(
                'Send limit amount must be positive.',
                ['amount' => ['Amount must be greater than zero.']],
            );
        }

        $response = $this->client->post('send/send_limits', [
            'amount' => $amount,
        ]);

        return SendLimitData::from($response);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listSendLimits(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'send/send_limits' . ($queryString ? "?{$queryString}" : '');

        return $this->client->get($endpoint);
    }

    /**
     * @return array<string, mixed>
     */
    public function resendApprovalRequest(int $id): array
    {
        return $this->client->post("send/send_limits/{$id}/resend_approval_requests");
    }

    public function createBankAccount(
        string $bankCode,
        string $accountNumber,
        string $accountHolderName,
        string $reference,
    ): BankAccountData {
        $data = [
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
            'name' => $accountHolderName,
            'reference' => $reference,
        ];

        $response = $this->client->post('send/bank_accounts', $data);

        return BankAccountData::from($response);
    }

    public function getBankAccount(int $id): BankAccountData
    {
        $response = $this->client->get("send/bank_accounts/{$id}");

        return BankAccountData::from($response);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listBankAccounts(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'send/bank_accounts' . ($queryString ? "?{$queryString}" : '');

        return $this->client->get($endpoint);
    }

    public function deleteBankAccount(int $id): void
    {
        $this->client->delete("send/bank_accounts/{$id}");
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
    public function getGroup(int $id): array
    {
        return $this->client->get("send/groups/{$id}");
    }

    /**
     * Update a group
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateGroup(int $id, array $data): array
    {
        return $this->client->patch("send/groups/{$id}", $data);
    }

    /**
     * Delete a group
     */
    public function deleteGroup(int $id): void
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
        $endpoint = 'send/groups' . ($queryString ? '?' . $queryString : '');

        return $this->client->get($endpoint);
    }

    /**
     * Create a webhook for CHIP Send
     *
     * @param  array<string, mixed>  $data
     */
    public function createSendWebhook(array $data): SendWebhookData
    {
        $response = $this->client->post('webhooks', $data);

        return SendWebhookData::from($response);
    }

    /**
     * Get a CHIP Send webhook
     */
    public function getSendWebhook(int $id): SendWebhookData
    {
        $response = $this->client->get("webhooks/{$id}");

        return SendWebhookData::from($response);
    }

    /**
     * Update a CHIP Send webhook
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSendWebhook(int $id, array $data): SendWebhookData
    {
        $response = $this->client->patch("webhooks/{$id}", $data);

        return SendWebhookData::from($response);
    }

    /**
     * Delete a CHIP Send webhook
     */
    public function deleteSendWebhook(int $id): void
    {
        $this->client->delete("webhooks/{$id}");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{results: array<int, SendWebhookData>, meta?: array<string, mixed>}
     */
    public function listSendWebhooks(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'webhooks' . ($queryString ? '?' . $queryString : '');

        $response = $this->client->get($endpoint);

        if (isset($response['results']) && is_array($response['results'])) {
            $response['results'] = array_map(static fn (array $item) => SendWebhookData::from($item), $response['results']);

            return $response;
        }

        throw new ChipValidationException('CHIP Send webhook list response must contain a results array.');
    }

    /**
     * Delete a send instruction
     */
    public function deleteSendInstruction(int $id): void
    {
        $this->client->delete("send/send_instructions/{$id}");
    }

    /**
     * Resend a send instruction webhook
     *
     * @return array<string, mixed>
     */
    public function resendSendInstructionWebhook(int $id): array
    {
        return $this->client->post("send/send_instructions/{$id}/resend_webhook_event");
    }

    /**
     * Resend a bank account webhook
     *
     * @return array<string, mixed>
     */
    public function resendBankAccountWebhook(int $id): array
    {
        return $this->client->post("send/bank_accounts/{$id}/resend_webhook_event");
    }

    /**
     * Validate send instruction parameters.
     *
     * @throws ChipValidationException If validation fails
     */
    private function validateSendInstruction(
        int $amountInCents,
        string $email,
        string $description,
        string $reference
    ): void {
        $errors = [];

        if ($amountInCents <= 0) {
            $errors['amount'] = ['Amount must be a positive integer'];
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Invalid email address'];
        }

        if (empty(mb_trim($description))) {
            $errors['description'] = ['Description is required'];
        }

        if (empty(mb_trim($reference))) {
            $errors['reference'] = ['Reference is required'];
        }

        if (! empty($errors)) {
            throw new ChipValidationException('Send instruction validation failed', $errors);
        }
    }
}
