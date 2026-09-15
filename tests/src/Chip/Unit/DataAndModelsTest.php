<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipSendClient;
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

    it('validates email in send instruction', function (): void {
        $this->service->createSendInstruction(
            10000,
            1,
            'Test',
            'REF123',
            'invalid-email'
        );
    })->throws(ChipValidationException::class);
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
    it('returns amount as Money without floating point conversion', function (): void {
        $instruction = new SendInstruction(['amount' => '100.50']);

        expect($instruction->amountMoney)->toBeInstanceOf(Money::class)
            ->and($instruction->amountMoney->getAmount())->toBe(10050);
    });

    it('rounds major-unit decimal boundaries half-up', function (): void {
        $instruction = new SendInstruction(['amount' => '100.005']);

        expect($instruction->amountInMinorUnits())->toBe(10001);
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
            'amount' => '100.01',
            'net_amount' => '99.99',
            'fee' => '0.02',
            'currency' => 'MYR',
        ]);

        expect($limit->amountMoney)->toBeInstanceOf(Money::class);
        expect($limit->amountMoney->getAmount())->toBe(10001);

        expect($limit->netAmountMoney)->toBeInstanceOf(Money::class);
        expect($limit->netAmountMoney->getAmount())->toBe(9999);
        expect($limit->feeMoney)->toBeInstanceOf(Money::class);
        expect($limit->feeMoney->getAmount())->toBe(2);
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
