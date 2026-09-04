<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\Data\BankAccountData;
use AIArmada\Chip\Data\SendInstructionData;
use AIArmada\Chip\Data\SendLimitData;
use AIArmada\Chip\Data\SendWebhookData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Services\ChipSendService;

describe('ChipSendService', function (): void {
    beforeEach(function (): void {
        $this->client = Mockery::mock(ChipSendClient::class);
        $this->service = new ChipSendService($this->client);
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('creates a send instruction using the documented request fields', function (): void {
        $instructionData = [
            'id' => 50,
            'bank_account_id' => 1,
            'amount' => '500.00',
            'state' => 'completed',
            'email' => 'test@example.com',
            'description' => 'Payment for services',
            'reference' => 'TRANSFER_001',
            'send_recipient_receipt' => true,
            'receipt_url' => null,
            'slug' => null,
            'created_at' => '2023-07-20T10:41:25.190Z',
            'updated_at' => '2023-07-20T10:41:25.302Z',
        ];

        $this->client->shouldReceive('post')
            ->with('send/send_instructions', [
                'bank_account_id' => 1,
                'amount' => '500.00',
                'description' => 'Payment for services',
                'reference' => 'TRANSFER_001',
                'email' => 'test@example.com',
                'send_recipient_receipt' => true,
            ])
            ->andReturn($instructionData);

        $instruction = $this->service->createSendInstruction(
            50000,
            1,
            'Payment for services',
            'TRANSFER_001',
            'test@example.com',
            true,
        );

        expect($instruction)->toBeInstanceOf(SendInstructionData::class)
            ->and($instruction->id)->toBe(50)
            ->and($instruction->amount)->toBe('500.00')
            ->and($instruction->state)->toBe('completed')
            ->and($instruction->send_recipient_receipt)->toBeTrue();
    });

    it('validates send instruction parameters', function (): void {
        $this->service->createSendInstruction(-100, 1, 'Test', 'REF123', 'test@example.com');
    })->throws(ChipValidationException::class);

    it('retrieves and lists send instructions', function (): void {
        $instructionData = [
            'id' => 50,
            'bank_account_id' => 1,
            'amount' => '500.00',
            'state' => 'completed',
            'email' => 'test@example.com',
            'description' => 'Payment for services',
            'reference' => 'TRANSFER_001',
            'send_recipient_receipt' => false,
            'receipt_url' => null,
            'slug' => null,
            'created_at' => '2023-07-20T10:41:25.190Z',
            'updated_at' => '2023-07-20T10:41:25.302Z',
        ];

        $this->client->shouldReceive('get')->with('send/send_instructions/50')->once()->andReturn($instructionData);
        expect($this->service->getSendInstruction(50))->toBeInstanceOf(SendInstructionData::class);

        $this->client->shouldReceive('get')->with('send/send_instructions?state=completed&page=2')->once()->andReturn(['results' => []]);
        expect($this->service->listSendInstructions(['state' => 'completed', 'page' => 2]))->toBe(['results' => []]);
    });

    it('creates and manages bank accounts using documented integer IDs', function (): void {
        $accountData = [
            'id' => 84,
            'status' => 'verified',
            'account_number' => '157380111111',
            'bank_code' => 'MBBEMYKL',
            'group_id' => null,
            'name' => 'Ahmad Pintu',
            'reference' => 'recipient-84',
            'created_at' => '2023-07-20T08:59:10.766Z',
            'is_debiting_account' => false,
            'is_crediting_account' => true,
            'updated_at' => '2023-07-20T08:59:10.766Z',
            'deleted_at' => null,
            'rejection_reason' => null,
        ];

        $this->client->shouldReceive('post')->with('send/bank_accounts', [
            'bank_code' => 'MBBEMYKL',
            'account_number' => '157380111111',
            'name' => 'Ahmad Pintu',
            'reference' => 'recipient-84',
        ])->once()->andReturn($accountData);
        expect($this->service->createBankAccount('MBBEMYKL', '157380111111', 'Ahmad Pintu', 'recipient-84'))
            ->toBeInstanceOf(BankAccountData::class);

        $this->client->shouldReceive('get')->with('send/bank_accounts/84')->once()->andReturn($accountData);
        expect($this->service->getBankAccount(84))->toBeInstanceOf(BankAccountData::class);

        $this->client->shouldReceive('get')->with('send/bank_accounts?status=verified')->once()->andReturn(['results' => []]);
        expect($this->service->listBankAccounts(['status' => 'verified']))->toBe(['results' => []]);

        $this->client->shouldReceive('delete')->with('send/bank_accounts/84')->once();
        $this->service->deleteBankAccount(84);

        $this->client->shouldReceive('post')->with('send/bank_accounts/84/resend_webhook_event')->once()->andReturn(['success' => true]);
        expect($this->service->resendBankAccountWebhook(84))->toBe(['success' => true]);
    });

    it('retrieves, requests, lists, and resends Send limits', function (): void {
        $limitPayload = [
            'id' => 9,
            'currency' => 'MYR',
            'fee_type' => 'flat',
            'transaction_type' => 'out',
            'amount' => 100,
            'fee' => 1,
            'net_amount' => 99,
            'status' => 'approved',
            'approvals_required' => 1,
            'approvals_received' => 1,
            'from_settlement' => '2024-04-01',
            'created_at' => '2024-04-01T10:00:00Z',
            'updated_at' => '2024-04-01T10:10:00Z',
        ];

        $this->client->shouldReceive('get')->with('send/send_limits/9')->once()->andReturn($limitPayload);
        expect($this->service->getSendLimit(9))->toBeInstanceOf(SendLimitData::class);

        $this->client->shouldReceive('post')->with('send/send_limits', ['amount' => 1000.5])->once()->andReturn($limitPayload);
        expect($this->service->increaseBudgetAllocation(1000.5))->toBeInstanceOf(SendLimitData::class);

        $this->client->shouldReceive('get')->with('send/send_limits?status=pending')->once()->andReturn(['results' => []]);
        expect($this->service->listSendLimits(['status' => 'pending']))->toBe(['results' => []]);

        $this->client->shouldReceive('post')->with('send/send_limits/9/resend_approval_requests')->once()->andReturn(['success' => true]);
        expect($this->service->resendApprovalRequest(9))->toBe(['success' => true]);
    });

    it('deletes send instructions and resends their webhook event', function (): void {
        $this->client->shouldReceive('delete')->with('send/send_instructions/50')->once();
        $this->service->deleteSendInstruction(50);

        $this->client->shouldReceive('post')->with('send/send_instructions/50/resend_webhook_event')->once()->andReturn(['success' => true]);
        expect($this->service->resendSendInstructionWebhook(50))->toBe(['success' => true]);
    });

    it('manages groups and uses PATCH for group updates', function (): void {
        $data = ['id' => 1, 'name' => 'Test Group'];

        $this->client->shouldReceive('post')->with('send/groups', ['name' => 'Test Group'])->once()->andReturn($data);
        expect($this->service->createGroup(['name' => 'Test Group']))->toBe($data);

        $this->client->shouldReceive('get')->with('send/groups/1')->once()->andReturn($data);
        expect($this->service->getGroup(1))->toBe($data);

        $this->client->shouldReceive('patch')->with('send/groups/1', ['name' => 'Updated'])->once()->andReturn(['id' => 1, 'name' => 'Updated']);
        expect($this->service->updateGroup(1, ['name' => 'Updated']))->toBe(['id' => 1, 'name' => 'Updated']);

        $this->client->shouldReceive('delete')->with('send/groups/1')->once();
        $this->service->deleteGroup(1);

        $this->client->shouldReceive('get')->with('send/groups')->once()->andReturn(['results' => []]);
        expect($this->service->listGroups())->toBe(['results' => []]);
    });

    it('manages Send webhooks at the documented root endpoint', function (): void {
        $webhookPayload = [
            'id' => 1,
            'name' => 'Primary',
            'public_key' => 'pk',
            'callback_url' => 'https://example.com',
            'email' => 'ops@example.com',
            'event_hooks' => ['send_instruction_status'],
            'created_at' => '2024-04-01T00:00:00Z',
            'updated_at' => '2024-04-01T00:00:00Z',
        ];

        $this->client->shouldReceive('post')->with('webhooks', ['name' => 'Primary'])->once()->andReturn($webhookPayload);
        expect($this->service->createSendWebhook(['name' => 'Primary']))->toBeInstanceOf(SendWebhookData::class);

        $this->client->shouldReceive('get')->with('webhooks/1')->once()->andReturn($webhookPayload);
        expect($this->service->getSendWebhook(1))->toBeInstanceOf(SendWebhookData::class);

        $this->client->shouldReceive('patch')->with('webhooks/1', ['event_hooks' => ['bank_account_status']])->once()->andReturn($webhookPayload);
        expect($this->service->updateSendWebhook(1, ['event_hooks' => ['bank_account_status']]))->toBeInstanceOf(SendWebhookData::class);

        $this->client->shouldReceive('delete')->with('webhooks/1')->once();
        $this->service->deleteSendWebhook(1);

        $this->client->shouldReceive('get')->with('webhooks')->once()->andReturn(['results' => [$webhookPayload], 'meta' => ['total' => 1]]);
        $list = $this->service->listSendWebhooks();

        expect($list['results'][0])->toBeInstanceOf(SendWebhookData::class)
            ->and($list['meta']['total'])->toBe(1);
    });
});
