<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\TransactionData;

describe('TransactionData data object', function (): void {
    it('detects failed attempts and returns last attempt', function (): void {
        $data = [
            'payment_method' => 'fpx',
            'country' => 'MY',
            'extra' => ['bank' => 'Maybank'],
            'attempts' => [
                ['id' => 'attempt_1', 'successful' => false],
                ['id' => 'attempt_2', 'successful' => true],
            ],
        ];

        $transaction = TransactionData::fromArray($data);

        expect($transaction->getLastAttempt())->toBe(['id' => 'attempt_1', 'successful' => false]);
        expect($transaction->hasFailedAttempts())->toBeTrue();
        expect($transaction->getFailedAttempts())->toHaveCount(1);
        expect($transaction->getFailedAttempts()[0]['id'])->toBe('attempt_1');
    });

    it('handles empty attempts gracefully', function (): void {
        $transaction = TransactionData::fromArray([]);

        expect($transaction->getLastAttempt())->toBeNull();
        expect($transaction->hasFailedAttempts())->toBeFalse();
        expect($transaction->toArray())->toMatchArray([
            'payment_method' => null,
            'extra' => [],
            'country' => null,
            'attempts' => [],
        ]);
    });
});
