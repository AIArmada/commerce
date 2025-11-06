<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\DataObjects\BankAccount;
use AIArmada\Chip\DataObjects\SendInstruction;
use AIArmada\Chip\DataObjects\SendLimit;
use AIArmada\Chip\DataObjects\SendWebhook;
use AIArmada\Chip\Services\ChipSendService;

describe('ChipSendService', function (): void {
    beforeEach(function (): void {
        $this->client = Mockery::mock(ChipSendClient::class);
        $this->service = new ChipSendService($this->client);
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('can create a send instruction', function (): void {
        $instructionData = [
            'id' => 50,
            'bank_account_id' => 1,
            'amount' => '500.00',
            'state' => 'completed',
            'email' => 'test@example.com',
            'description' => 'Payment for services',
            'reference' => 'TRANSFER_001',
            'created_at' => '2023-07-20T10:41:25.190Z',
            'updated_at' => '2023-07-20T10:41:25.302Z',
        ];

        $this->client->shouldReceive('post')
            ->with('send/send_instructions', [
                'bank_account_id' => '1',
                'amount' => '500.00',
                'currency' => 'MYR',
                'description' => 'Payment for services',
                'reference' => 'TRANSFER_001',
                'email' => 'test@example.com',
            ])
            ->andReturn($instructionData);

        $instruction = $this->service->createSendInstruction(
            50000, // amount in cents
            'MYR',
            '1', // recipient bank account id
            'Payment for services',
            'TRANSFER_001',
            'test@example.com'
        );

        expect($instruction)->toBeInstanceOf(SendInstruction::class);
        expect($instruction->id)->toBe(50);
        expect($instruction->amount)->toBe('500.00');
        expect($instruction->state)->toBe('completed');
        expect($instruction->description)->toBe('Payment for services');
        expect($instruction->reference)->toBe('TRANSFER_001');
        expect($instruction->email)->toBe('test@example.com');
    });

    it('can retrieve a send instruction', function (): void {
        $instructionData = [
            'id' => 50,
            'bank_account_id' => 1,
            'amount' => '500.00',
            'state' => 'completed',
            'email' => 'test@example.com',
            'description' => 'Payment for services',
            'reference' => 'TRANSFER_001',
            'created_at' => '2023-07-20T10:41:25.190Z',
            'updated_at' => '2023-07-20T10:41:25.302Z',
        ];

        $this->client->shouldReceive('get')
            ->with('send/send_instructions/50')
            ->andReturn($instructionData);

        $instruction = $this->service->getSendInstruction('50');

        expect($instruction)->toBeInstanceOf(SendInstruction::class);
        expect($instruction->id)->toBe(50);
        expect($instruction->state)->toBe('completed');
    });

    it('can create a bank account', function (): void {
        $accountData = [
            'id' => 84,
            'status' => 'verified',
            'account_number' => '157380111111',
            'bank_code' => 'MBBEMYKL',
            'group_id' => null,
            'name' => 'Ahmad Pintu',
            'reference' => null,
            'created_at' => '2023-07-20T08:59:10.766Z',
            'is_debiting_account' => false,
            'is_crediting_account' => false,
            'updated_at' => '2023-07-20T08:59:10.766Z',
            'deleted_at' => null,
            'rejection_reason' => null,
        ];

        $this->client->shouldReceive('post')
            ->with('send/bank_accounts', [
                'bank_code' => 'MBBEMYKL',
                'account_number' => '157380111111',
                'name' => 'Ahmad Pintu',
            ])
            ->andReturn($accountData);

        $account = $this->service->createBankAccount(
            'MBBEMYKL',
            '157380111111',
            'Ahmad Pintu'
        );

        expect($account)->toBeInstanceOf(BankAccount::class);
        expect($account->id)->toBe(84);
        expect($account->status)->toBe('verified');
        expect($account->account_number)->toBe('157380111111');
        expect($account->bank_code)->toBe('MBBEMYKL');
        expect($account->name)->toBe('Ahmad Pintu');
    });

    it('lists accounts and send instructions with filters', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('send/accounts')
            ->andReturn(['accounts' => []]);

        expect($this->service->listAccounts())->toBe(['accounts' => []]);

        $this->client->shouldReceive('get')
            ->once()
            ->with('send/send_instructions?state=completed&page=2')
            ->andReturn(['instructions' => []]);

        expect($this->service->listSendInstructions(['state' => 'completed', 'page' => 2]))
            ->toBe(['instructions' => []]);
    });

    it('retrieves a send limit', function (): void {
        $limitPayload = [
            'id' => 9,
            'currency' => 'MYR',
            'fee_type' => 'flat',
            'transaction_type' => 'out',
            'amount' => 10000,
            'fee' => 100,
            'net_amount' => 9900,
            'status' => 'success',
            'approvals_required' => 1,
            'approvals_received' => 1,
            'from_settlement' => '2024-04-01',
            'created_at' => '2024-04-01T10:00:00Z',
            'updated_at' => '2024-04-01T10:10:00Z',
        ];

        $this->client->shouldReceive('get')
            ->once()
            ->with('send/send_limits/9')
            ->andReturn($limitPayload);

        $limit = $this->service->getSendLimit(9);

        expect($limit)->toBeInstanceOf(SendLimit::class);
        expect($limit->currency)->toBe('MYR');
        expect($limit->getNetAmountInMajorUnits())->toBe(99.0);
    });

    it('manages bank account lifecycle', function (): void {
        $accountPayload = [
            'id' => 84,
            'status' => 'verified',
            'account_number' => '157380111111',
            'bank_code' => 'MBBEMYKL',
            'group_id' => null,
            'name' => 'Ahmad Pintu',
            'reference' => null,
            'created_at' => '2023-07-20T08:59:10.766Z',
            'is_debiting_account' => false,
            'is_crediting_account' => false,
            'updated_at' => '2023-07-20T08:59:10.766Z',
            'deleted_at' => null,
            'rejection_reason' => null,
        ];

        $this->client->shouldReceive('get')
            ->once()
            ->with('send/bank_accounts/84')
            ->andReturn($accountPayload);

        $account = $this->service->getBankAccount('84');

        expect($account)->toBeInstanceOf(BankAccount::class);

        $this->client->shouldReceive('get')
            ->once()
            ->with('send/bank_accounts?status=verified')
            ->andReturn(['accounts' => []]);

        expect($this->service->listBankAccounts(['status' => 'verified']))
            ->toBe(['accounts' => []]);

        $this->client->shouldReceive('put')
            ->once()
            ->with('send/bank_accounts/84', ['name' => 'New Name'])
            ->andReturn($accountPayload);

        expect($this->service->updateBankAccount('84', ['name' => 'New Name']))
            ->toBeInstanceOf(BankAccount::class);

        $this->client->shouldReceive('delete')
            ->once()
            ->with('send/bank_accounts/84');

        $this->service->deleteBankAccount('84');

        $this->client->shouldReceive('post')
            ->once()
            ->with('send/bank_accounts/84/resend_webhook')
            ->andReturn(['status' => 'queued']);

        expect($this->service->resendBankAccountWebhook('84'))
            ->toBe(['status' => 'queued']);
    });

    it('handles send instruction cancellations and deletions', function (): void {
        $instructionPayload = ['data' => [
            'id' => 'si_100',
            'bank_account_id' => 84,
            'amount' => '100.00',
            'state' => 'cancelled',
            'email' => 'recipient@example.com',
            'description' => 'Refund',
            'reference' => 'REF-100',
            'created_at' => '2023-07-20T10:41:25.190Z',
            'updated_at' => '2023-07-20T10:41:25.302Z',
        ]];

        $this->client->shouldReceive('post')
            ->once()
            ->with('send/send_instructions/si_100/cancel')
            ->andReturn($instructionPayload);

        $cancelled = $this->service->cancelSendInstruction('si_100');

        expect($cancelled)->toBeInstanceOf(SendInstruction::class);
        expect($cancelled->state)->toBe('cancelled');

        $this->client->shouldReceive('delete')
            ->once()
            ->with('send/send_instructions/si_100');

        $this->service->deleteSendInstruction('si_100');

        $this->client->shouldReceive('post')
            ->once()
            ->with('send/send_instructions/si_100/resend_webhook')
            ->andReturn(['status' => 'queued']);

        expect($this->service->resendSendInstructionWebhook('si_100'))
            ->toBe(['status' => 'queued']);
    });

    it('manages send groups and webhooks', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('send/groups', ['name' => 'VIP'])
            ->andReturn(['id' => 'group_1']);

        expect($this->service->createGroup(['name' => 'VIP']))
            ->toBe(['id' => 'group_1']);

        $this->client->shouldReceive('get')
            ->once()
            ->with('send/groups/group_1')
            ->andReturn(['id' => 'group_1']);

        expect($this->service->getGroup('group_1'))
            ->toBe(['id' => 'group_1']);

        $this->client->shouldReceive('put')
            ->once()
            ->with('send/groups/group_1', ['name' => 'Priority'])
            ->andReturn(['id' => 'group_1', 'name' => 'Priority']);

        expect($this->service->updateGroup('group_1', ['name' => 'Priority']))
            ->toBe(['id' => 'group_1', 'name' => 'Priority']);

        $this->client->shouldReceive('delete')
            ->once()
            ->with('send/groups/group_1');

        $this->service->deleteGroup('group_1');

        $this->client->shouldReceive('get')
            ->once()
            ->with('send/groups?per_page=10')
            ->andReturn(['data' => []]);

        expect($this->service->listGroups(['per_page' => 10]))
            ->toBe(['data' => []]);

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

        $this->client->shouldReceive('post')
            ->once()
            ->with('send/webhooks', ['url' => 'https://example.com'])
            ->andReturn($webhookPayload);

        $createdWebhook = $this->service->createSendWebhook(['url' => 'https://example.com']);

        expect($createdWebhook)->toBeInstanceOf(SendWebhook::class)
            ->and($createdWebhook->handlesEvent('send_instruction_status'))->toBeTrue();

        $this->client->shouldReceive('get')
            ->once()
            ->with('send/webhooks/wh_1')
            ->andReturn($webhookPayload);

        expect($this->service->getSendWebhook('wh_1'))
            ->toBeInstanceOf(SendWebhook::class);

        $updatedPayload = $webhookPayload;
        $updatedPayload['event_hooks'] = ['bank_account_status'];

        $this->client->shouldReceive('put')
            ->once()
            ->with('send/webhooks/wh_1', ['events' => ['bank_account_status']])
            ->andReturn($updatedPayload);

        $updated = $this->service->updateSendWebhook('wh_1', ['events' => ['bank_account_status']]);

        expect($updated)->toBeInstanceOf(SendWebhook::class)
            ->and($updated->handlesEvent('bank_account_status'))->toBeTrue();

        $this->client->shouldReceive('delete')
            ->once()
            ->with('send/webhooks/wh_1');

        $this->service->deleteSendWebhook('wh_1');

        $listPayload = [
            'data' => [
                $webhookPayload,
            ],
            'meta' => ['total' => 1],
        ];

        $this->client->shouldReceive('get')
            ->once()
            ->with('send/webhooks?type=callback')
            ->andReturn($listPayload);

        $list = $this->service->listSendWebhooks(['type' => 'callback']);

        expect($list)->toHaveKey('data');
        expect($list['data'][0])->toBeInstanceOf(SendWebhook::class);
        expect($list['meta']['total'])->toBe(1);
    });
});
