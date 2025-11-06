<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\SendInstruction;

describe('SendInstruction data object', function (): void {
    it('creates a send instruction from array data', function (): void {
        $data = [
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

        $instruction = SendInstruction::fromArray($data);

        expect($instruction->id)->toBe(50);
        expect($instruction->bank_account_id)->toBe(1);
        expect($instruction->amount)->toBe('500.00');
        expect($instruction->state)->toBe('completed');
        expect($instruction->email)->toBe('test@example.com');
        expect($instruction->description)->toBe('Payment for services');
        expect($instruction->reference)->toBe('TRANSFER_001');
        expect($instruction->created_at)->toBe('2023-07-20T10:41:25.190Z');
    });

    it('handles rejected send instruction', function (): void {
        $instruction = SendInstruction::fromArray([
            'id' => 51,
            'bank_account_id' => 2,
            'amount' => '250.00',
            'state' => 'rejected',
            'email' => 'test2@example.com',
            'description' => 'Another payment',
            'reference' => 'TRANSFER_002',
            'created_at' => '2023-07-20T11:41:25.190Z',
            'updated_at' => '2023-07-20T11:41:25.302Z',
        ]);

        expect($instruction->state)->toBe('rejected');
        expect($instruction->isRejected())->toBeTrue();
        expect($instruction->isCompleted())->toBeFalse();
    });

    it('provides amount helpers and array export', function (): void {
        $instruction = SendInstruction::fromArray([
            'id' => 52,
            'bank_account_id' => 3,
            'amount' => '125.50',
            'state' => 'executing',
            'email' => 'ops@example.com',
            'description' => 'Vendor payment',
            'reference' => 'TRANSFER_003',
            'receipt_url' => 'https://example.com/receipt.pdf',
            'slug' => 'transfer-003',
            'created_at' => '2023-07-20T12:00:00Z',
            'updated_at' => '2023-07-20T12:05:00Z',
        ]);

        expect($instruction->getCreatedAt()->toDateTimeString())->toBe('2023-07-20 12:00:00');
        expect($instruction->getUpdatedAt()->toDateTimeString())->toBe('2023-07-20 12:05:00');
        expect($instruction->getAmountInMajorUnits())->toBe(125.50);
        expect($instruction->getAmountInMinorUnits())->toBe(12550);
        expect($instruction->isPending())->toBeTrue();
        expect($instruction->toArray())->toMatchArray([
            'reference' => 'TRANSFER_003',
            'receipt_url' => 'https://example.com/receipt.pdf',
            'slug' => 'transfer-003',
        ]);
    });
});
