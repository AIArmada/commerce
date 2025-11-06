<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\BankAccount;

describe('BankAccount data object', function (): void {
    it('creates a bank account from array data', function (): void {
        $data = [
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

        $account = BankAccount::fromArray($data);

        expect($account->id)->toBe(84);
        expect($account->status)->toBe('verified');
        expect($account->account_number)->toBe('157380111111');
        expect($account->bank_code)->toBe('MBBEMYKL');
        expect($account->name)->toBe('Ahmad Pintu');
        expect($account->isVerified())->toBeTrue();
        expect($account->isPending())->toBeFalse();
    });

    it('handles unverified bank account', function (): void {
        $account = BankAccount::fromArray([
            'id' => 85,
            'status' => 'pending',
            'account_number' => '157380222222',
            'bank_code' => 'MBBEMYKL',
            'group_id' => null,
            'name' => 'Siti Aminah',
            'reference' => null,
            'created_at' => '2023-07-20T09:59:10.766Z',
            'is_debiting_account' => false,
            'is_crediting_account' => false,
            'updated_at' => '2023-07-20T09:59:10.766Z',
            'deleted_at' => null,
            'rejection_reason' => null,
        ]);

        expect($account->isVerified())->toBeFalse();
        expect($account->isPending())->toBeTrue();
        expect($account->deleted_at)->toBeNull();
    });

    it('exposes date helpers and account capabilities', function (): void {
        $account = BankAccount::fromArray([
            'id' => 99,
            'status' => 'verified',
            'account_number' => '9876543210',
            'bank_code' => 'CIMBMYKL',
            'group_id' => 100,
            'name' => 'Operations Account',
            'reference' => 'OPS-01',
            'created_at' => '2024-01-01T00:00:00Z',
            'is_debiting_account' => true,
            'is_crediting_account' => true,
            'updated_at' => '2024-01-02T00:00:00Z',
            'deleted_at' => '2024-01-03T00:00:00Z',
            'rejection_reason' => null,
        ]);

        expect($account->getCreatedAt()->toDateString())->toBe('2024-01-01');
        expect($account->getUpdatedAt()->toDateString())->toBe('2024-01-02');
        expect($account->getDeletedAt()?->toDateString())->toBe('2024-01-03');
        expect($account->canReceivePayments())->toBeFalse();
        expect($account->canSendPayments())->toBeFalse();
        expect($account->isDeleted())->toBeTrue();
        expect($account->toArray()['reference'])->toBe('OPS-01');
    });
});
