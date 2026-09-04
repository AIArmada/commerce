<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\Data\BankAccountData;
use AIArmada\Chip\Data\ClientData;
use AIArmada\Chip\Data\SendInstructionData;
use AIArmada\Chip\Data\SendLimitData;
use AIArmada\Chip\Data\SendWebhookData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\Client;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Models\SendLimit;
use AIArmada\Chip\Services\ChipSendService;
use Akaunting\Money\Money;

describe('ChipSendService', function (): void {
    beforeEach(function (): void {
        $this->client = Mockery::mock(ChipSendClient::class);
        $this->service = new ChipSendService($this->client);
    });

    it('lists accounts', function (): void {
        $data = ['results' => [['id' => 1]]];
        $this->client->shouldReceive('get')->with('send/accounts')->once()->andReturn($data);

        expect($this->service->listAccounts())->toBe($data);
    });

    it('creates send instruction', function (): void {
        $responseData = [
            'id' => 1,
            'amount' => 100.00,
            'bank_account_id' => 1,
            'description' => 'Test',
            'reference' => 'REF123',
            'email' => 'test@example.com',
            'state' => 'received',
            'send_recipient_receipt' => false,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->client->shouldReceive('post')
            ->once()
            ->with('send/send_instructions', Mockery::on(function ($data) {
                return $data['amount'] === '100.00' &&
                    ! array_key_exists('currency', $data) &&
                    $data['email'] === 'test@example.com';
            }))
            ->andReturn($responseData);

        $result = $this->service->createSendInstruction(
            10000,
            1,
            'Test',
            'REF123',
            'test@example.com'
        );

        expect($result)->toBeInstanceOf(SendInstructionData::class);
    });

    it('validates send instruction parameters', function (): void {
        $this->service->createSendInstruction(
            -100, // Invalid amount
            1,
            'Test',
            'REF123',
            'test@example.com'
        );
    })->throws(ChipValidationException::class);

    it('validates email in send instruction', function (): void {
        $this->service->createSendInstruction(
            10000,
            1,
            'Test',
            'REF123',
            'invalid-email'
        );
    })->throws(ChipValidationException::class);

    it('gets send instruction', function (): void {
        $responseData = [
            'id' => 1,
            'amount' => 100.00,
            'bank_account_id' => 1,
            'description' => 'Test',
            'reference' => 'REF123',
            'email' => 'test@example.com',
            'state' => 'received',
            'send_recipient_receipt' => false,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->client->shouldReceive('get')->with('send/send_instructions/1')->once()->andReturn($responseData);

        $result = $this->service->getSendInstruction(1);
        expect($result)->toBeInstanceOf(SendInstructionData::class);
    });

    it('lists send instructions', function (): void {
        $data = ['results' => []];
        $this->client->shouldReceive('get')->with('send/send_instructions')->once()->andReturn($data);

        expect($this->service->listSendInstructions())->toBe($data);
    });

    it('gets send limit', function (): void {
        $responseData = [
            'id' => 1,
            'currency' => 'MYR',
            'fee_type' => 'transaction',
            'transaction_type' => 'transfer',
            'amount' => 100,
            'fee' => 1,
            'net_amount' => 99,
            'status' => 'approved',
            'approvals_required' => 1,
            'approvals_received' => 0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->client->shouldReceive('get')->with('send/send_limits/1')->once()->andReturn($responseData);

        $result = $this->service->getSendLimit(1);
        expect($result)->toBeInstanceOf(SendLimitData::class);
    });

    it('creates bank account', function (): void {
        $responseData = [
            'id' => 1,
            'account_number' => '1234567890',
            'bank_code' => 'MAYBANK',
            'name' => 'John Doe',
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'reference' => null,
            'is_debiting_account' => false,
            'is_crediting_account' => true,
        ];

        $this->client->shouldReceive('post')
            ->once()
            ->with('send/bank_accounts', Mockery::hasKey('bank_code'))
            ->andReturn($responseData);

        $result = $this->service->createBankAccount('MAYBANK', '1234567890', 'John Doe', 'recipient-1');

        expect($result)->toBeInstanceOf(BankAccountData::class);
    });

    it('gets bank account', function (): void {
        $responseData = [
            'id' => 1,
            'account_number' => '1234567890',
            'bank_code' => 'MAYBANK',
            'name' => 'John Doe',
            'status' => 'verified',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'reference' => null,
            'is_debiting_account' => false,
            'is_crediting_account' => true,
        ];

        $this->client->shouldReceive('get')->with('send/bank_accounts/1')->once()->andReturn($responseData);

        $result = $this->service->getBankAccount(1);
        expect($result)->toBeInstanceOf(BankAccountData::class);
    });

    it('deletes bank account', function (): void {
        $this->client->shouldReceive('delete')->with('send/bank_accounts/1')->once()->andReturn([]);

        $this->service->deleteBankAccount(1);
    });

    it('lists bank accounts', function (): void {
        $data = ['results' => []];
        $this->client->shouldReceive('get')->with('send/bank_accounts')->once()->andReturn($data);

        expect($this->service->listBankAccounts())->toBe($data);
    });

    it('manages groups', function (): void {
        $data = ['id' => 1, 'name' => 'Test Group'];

        $this->client->shouldReceive('post')->with('send/groups', $data)->once()->andReturn($data);
        expect($this->service->createGroup($data))->toBe($data);

        $this->client->shouldReceive('get')->with('send/groups/1')->once()->andReturn($data);
        expect($this->service->getGroup(1))->toBe($data);

        $this->client->shouldReceive('patch')->with('send/groups/1', ['name' => 'Updated'])->once()->andReturn(['id' => 1, 'name' => 'Updated']);
        expect($this->service->updateGroup(1, ['name' => 'Updated']))->toBe(['id' => 1, 'name' => 'Updated']);

        $this->client->shouldReceive('delete')->with('send/groups/1')->once();
        $this->service->deleteGroup(1);

        $this->client->shouldReceive('get')->with('send/groups')->once()->andReturn(['results' => []]);
        expect($this->service->listGroups())->toBe(['results' => []]);
    });

    it('manages send webhooks', function (): void {
        $webhookData = [
            'id' => 1,
            'name' => 'Webhook 1',
            'public_key' => 'pk_123',
            'callback_url' => 'https://example.com/webhook',
            'email' => 'test@example.com',
            'event_hooks' => ['send_instruction_status'],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->client->shouldReceive('post')->with('webhooks', Mockery::type('array'))->once()->andReturn($webhookData);
        expect($this->service->createSendWebhook(['name' => 'Webhook 1']))->toBeInstanceOf(SendWebhookData::class);

        $this->client->shouldReceive('get')->with('webhooks/1')->once()->andReturn($webhookData);
        expect($this->service->getSendWebhook(1))->toBeInstanceOf(SendWebhookData::class);

        $this->client->shouldReceive('patch')->with('webhooks/1', Mockery::type('array'))->once()->andReturn($webhookData);
        expect($this->service->updateSendWebhook(1, ['event_hooks' => ['bank_account_status']]))->toBeInstanceOf(SendWebhookData::class);

        $this->client->shouldReceive('delete')->with('webhooks/1')->once();
        $this->service->deleteSendWebhook(1);

        $this->client->shouldReceive('get')->with('webhooks')->once()->andReturn(['results' => [$webhookData]]);
        $list = $this->service->listSendWebhooks();
        expect($list['results'][0])->toBeInstanceOf(SendWebhookData::class);
    });

    it('deletes send instruction and resends webhooks', function (): void {
        $this->client->shouldReceive('delete')->with('send/send_instructions/1')->once();
        $this->service->deleteSendInstruction(1);

        $this->client->shouldReceive('post')->with('send/send_instructions/1/resend_webhook_event')->once()->andReturn(['success' => true]);
        expect($this->service->resendSendInstructionWebhook(1))->toBe(['success' => true]);

        $this->client->shouldReceive('post')->with('send/bank_accounts/1/resend_webhook_event')->once()->andReturn(['success' => true]);
        expect($this->service->resendBankAccountWebhook(1))->toBe(['success' => true]);
    });
});

describe('ClientData', function (): void {
    it('creates from array', function (): void {
        $data = ClientData::from([
            'id' => 'client-1',
            'email' => 'client@example.com',
            'phone' => '+1234567890',
            'full_name' => 'John Doe',
            'country' => 'MY',
            'default_currency' => 'MYR',
            'bank_account' => null,
            'personal_code' => '123123',
            'legal_name' => 'John Doe Legal',
            'brand_id' => 'brand-1',
            'cc' => [],
            'bcc' => [],
            'metadata' => [],
            'notes' => 'Test note',
            'created_on' => time(),
            'updated_on' => time(),
        ]);

        expect($data->id)->toBe('client-1');
        expect($data->email)->toBe('client@example.com');
        expect($data->fullName)->toBe('John Doe');
    });
});

describe('Client Model', function (): void {
    it('guards owner columns from mass assignment', function (): void {
        $client = new Client;
        expect($client->getGuarded())->toBe(['owner_type', 'owner_id']);
    });

    it('casts attributes correctly', function (): void {
        $client = new Client([
            'cc' => ['test@example.com'],
            'bcc' => ['admin@example.com'],
        ]);

        expect($client->cc)->toBe(['test@example.com']);
        expect($client->bcc)->toBe(['admin@example.com']);
    });
});

describe('BankAccount Model', function (): void {
    it('returns status color and label', function (): void {
        $account = new BankAccount(['status' => 'verified']);
        expect($account->statusColor())->toBe('success');
        expect($account->statusLabel())->toBe('Verified');

        $account->status = 'pending';
        expect($account->statusColor())->toBe('warning');

        $account->status = 'rejected';
        expect($account->statusColor())->toBe('danger');
    });

    it('has correct table name', function (): void {
        Config::set('chip.database.table_prefix', 'chip_');
        $account = new BankAccount;
        expect($account->getTable())->toBe('chip_bank_accounts');
    });
});

describe('SendInstruction Model', function (): void {
    it('returns amount numeric', function (): void {
        $instruction = new SendInstruction(['amount' => '100.50']);
        expect($instruction->amountNumeric)->toBe(100.50);
    });

    it('returns state label and color', function (): void {
        $instruction = new SendInstruction(['state' => 'completed']);
        expect($instruction->stateLabel)->toBe('Completed');
        expect($instruction->stateColor())->toBe('success');

        $instruction->state = 'received';
        expect($instruction->stateColor())->toBe('warning');

        $instruction->state = 'rejected';
        expect($instruction->stateColor())->toBe('danger');
    });

    it('has correct table name', function (): void {
        $instruction = new SendInstruction;
        expect($instruction->getTable())->toBe('chip_send_instructions');
    });
});

describe('SendLimit Model', function (): void {
    it('converts amounts to Money objects', function (): void {
        $limit = new SendLimit([
            'amount' => 100,
            'net_amount' => 99,
            'fee' => 1,
            'currency' => 'MYR',
        ]);

        expect($limit->amountMoney)->toBeInstanceOf(Money::class);
        expect($limit->amountMoney->getAmount())->toBe(10000);

        expect($limit->netAmountMoney)->toBeInstanceOf(Money::class);
        expect($limit->feeMoney)->toBeInstanceOf(Money::class);
    });

    it('returns status color', function (): void {
        $limit = new SendLimit(['status' => 'approved']);
        expect($limit->statusColor())->toBe('success');

        $limit->status = 'pending';
        expect($limit->statusColor())->toBe('warning');

        $limit->status = 'expired';
        expect($limit->statusColor())->toBe('danger');
    });
});
