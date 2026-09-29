<?php

declare(strict_types=1);

use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('FakeChipClient idempotency', function (): void {
    it('dedupes repeated creates with the same second-argument key', function (): void {
        $fake = new FakeChipClient;

        $first = $fake->createPurchase(['purchase' => ['currency' => 'MYR']], 'fake-key-1');
        $second = $fake->createPurchase(['purchase' => ['currency' => 'MYR']], 'fake-key-1');

        expect($second['id'])->toBe($first['id'])
            ->and($fake->getPurchases())->toHaveCount(1);
    });

    it('dedupes repeated creates with the array-staged key', function (): void {
        $fake = new FakeChipClient;

        $data = ['purchase' => ['currency' => 'MYR'], 'idempotency_key' => 'fake-key-2'];

        $first = $fake->createPurchase($data);
        $second = $fake->createPurchase($data);

        expect($second['id'])->toBe($first['id'])
            ->and($fake->getPurchases())->toHaveCount(1);
    });

    it('rejects a reused key with a different payload', function (): void {
        $fake = new FakeChipClient;

        $fake->createPurchase(['purchase' => ['currency' => 'MYR']], 'fake-key-3');

        expect(fn () => $fake->createPurchase(['purchase' => ['currency' => 'USD']], 'fake-key-3'))
            ->toThrow(ChipValidationException::class, 'different purchase payload');
    });

    it('dedupes reordered but equivalent payloads', function (): void {
        $fake = new FakeChipClient;

        $first = $fake->createPurchase(
            ['purchase' => ['currency' => 'MYR'], 'client' => ['email' => 'a@example.com']],
            'fake-key-5'
        );
        $second = $fake->createPurchase(
            ['client' => ['email' => 'a@example.com'], 'purchase' => ['currency' => 'MYR']],
            'fake-key-5'
        );

        expect($second['id'])->toBe($first['id']);
    });

    it('dedupes the same key across staged and second-argument forms', function (): void {
        $fake = new FakeChipClient;

        $first = $fake->createPurchase(['purchase' => ['currency' => 'MYR'], 'idempotency_key' => 'fake-key-6']);
        $second = $fake->createPurchase(['purchase' => ['currency' => 'MYR']], 'fake-key-6');

        expect($second['id'])->toBe($first['id']);
    });

    it('creates a new purchase for a reused key after reset', function (): void {
        $fake = new FakeChipClient;

        $first = $fake->createPurchase(['purchase' => ['currency' => 'MYR']], 'fake-key-4');

        $fake->reset();

        $second = $fake->createPurchase(['purchase' => ['currency' => 'MYR']], 'fake-key-4');

        expect($second['id'])->not->toBe($first['id']);
    });
});
