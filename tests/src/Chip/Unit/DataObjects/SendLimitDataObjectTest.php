<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\SendLimit;

describe('SendLimit data object', function (): void {
    it('normalises API payload', function (): void {
        $payload = [
            'id' => 7,
            'currency' => 'MYR',
            'fee_type' => 'flat',
            'transaction_type' => 'out',
            'amount' => 12345,
            'fee' => 100,
            'net_amount' => 12245,
            'status' => 'success',
            'approvals_required' => 2,
            'approvals_received' => 1,
            'from_settlement' => '2024-04-01',
            'created_at' => '2024-04-01T12:00:00Z',
            'updated_at' => '2024-04-02T12:00:00Z',
        ];

        $limit = SendLimit::fromArray($payload);

        expect($limit->id)->toBe(7)
            ->and($limit->currency)->toBe('MYR')
            ->and($limit->fee_type)->toBe('flat')
            ->and($limit->transaction_type)->toBe('out')
            ->and($limit->amount)->toBe(12345)
            ->and($limit->fee)->toBe(100)
            ->and($limit->net_amount)->toBe(12245)
            ->and($limit->status)->toBe('success')
            ->and($limit->approvals_required)->toBe(2)
            ->and($limit->approvals_received)->toBe(1)
            ->and($limit->from_settlement)->toBe('2024-04-01');

        expect($limit->getAmountInMajorUnits())->toBe(123.45)
            ->and($limit->getFeeInMajorUnits())->toBe(1.0)
            ->and($limit->getNetAmountInMajorUnits())->toBe(122.45);

        expect($limit->getCreatedAt()->toIso8601ZuluString())->toBe('2024-04-01T12:00:00Z')
            ->and($limit->getUpdatedAt()->toIso8601ZuluString())->toBe('2024-04-02T12:00:00Z');

        expect($limit->toArray())->toMatchArray([
            'id' => 7,
            'currency' => 'MYR',
            'status' => 'success',
        ]);
    });
});
