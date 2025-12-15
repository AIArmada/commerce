<?php

declare(strict_types=1);

use AIArmada\Cart\AI\RecoveryStrategy;

describe('RecoveryStrategy', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $strategy = new RecoveryStrategy(
            id: 'strat-001',
            name: 'Email Recovery',
            type: 'email',
            delayMinutes: 60
        );

        expect($strategy->id)->toBe('strat-001')
            ->and($strategy->name)->toBe('Email Recovery')
            ->and($strategy->type)->toBe('email')
            ->and($strategy->delayMinutes)->toBe(60)
            ->and($strategy->parameters)->toBeEmpty()
            ->and($strategy->priority)->toBe(1);
    });

    it('can be instantiated with all parameters', function (): void {
        $strategy = new RecoveryStrategy(
            id: 'strat-002',
            name: 'Discount Strategy',
            type: 'popup',
            delayMinutes: 30,
            parameters: ['discount_percentage' => 15],
            priority: 2
        );

        expect($strategy->parameters)->toBe(['discount_percentage' => 15])
            ->and($strategy->priority)->toBe(2);
    });

    it('is immediate when delay is zero', function (): void {
        $strategy = new RecoveryStrategy('id', 'Name', 'popup', 0);

        expect($strategy->isImmediate())->toBeTrue();
    });

    it('is not immediate when delay is positive', function (): void {
        $strategy = new RecoveryStrategy('id', 'Name', 'email', 30);

        expect($strategy->isImmediate())->toBeFalse();
    });

    it('gets scheduled time correctly', function (): void {
        $strategy = new RecoveryStrategy('id', 'Name', 'email', 60);

        $scheduledTime = $strategy->getScheduledTime();

        expect($scheduledTime)->toBeInstanceOf(DateTimeInterface::class);
        // Check it's approximately 60 minutes in the future
        $diff = $scheduledTime->getTimestamp() - now()->getTimestamp();
        expect($diff)->toBeGreaterThanOrEqual(59 * 60)->toBeLessThanOrEqual(61 * 60);
    });

    it('has discount when discount_percentage is set', function (): void {
        $strategy = new RecoveryStrategy(
            id: 'id',
            name: 'Discount',
            type: 'email',
            delayMinutes: 30,
            parameters: ['discount_percentage' => 10]
        );

        expect($strategy->hasDiscount())->toBeTrue();
    });

    it('has discount when discount_amount is set', function (): void {
        $strategy = new RecoveryStrategy(
            id: 'id',
            name: 'Discount',
            type: 'email',
            delayMinutes: 30,
            parameters: ['discount_amount' => 500]
        );

        expect($strategy->hasDiscount())->toBeTrue();
    });

    it('has discount when dynamic_discount is true', function (): void {
        $strategy = new RecoveryStrategy(
            id: 'id',
            name: 'Discount',
            type: 'email',
            delayMinutes: 30,
            parameters: ['dynamic_discount' => true]
        );

        expect($strategy->hasDiscount())->toBeTrue();
    });

    it('does not have discount when no discount parameters', function (): void {
        $strategy = new RecoveryStrategy('id', 'Name', 'email', 30);

        expect($strategy->hasDiscount())->toBeFalse();
    });

    it('does not have discount when dynamic_discount is false', function (): void {
        $strategy = new RecoveryStrategy(
            id: 'id',
            name: 'Name',
            type: 'email',
            delayMinutes: 30,
            parameters: ['dynamic_discount' => false]
        );

        expect($strategy->hasDiscount())->toBeFalse();
    });

    it('gets discount percentage when set', function (): void {
        $strategy = new RecoveryStrategy(
            id: 'id',
            name: 'Discount',
            type: 'email',
            delayMinutes: 30,
            parameters: ['discount_percentage' => 15]
        );

        expect($strategy->getDiscountPercentage())->toBe(15);
    });

    it('returns null for discount percentage when not set', function (): void {
        $strategy = new RecoveryStrategy('id', 'Name', 'email', 30);

        expect($strategy->getDiscountPercentage())->toBeNull();
    });

    it('converts to array correctly', function (): void {
        $strategy = new RecoveryStrategy(
            id: 'strat-100',
            name: 'Recovery Email',
            type: 'email',
            delayMinutes: 45,
            parameters: ['discount_percentage' => 20],
            priority: 3
        );

        $array = $strategy->toArray();

        expect($array)->toBeArray()
            ->and($array['id'])->toBe('strat-100')
            ->and($array['name'])->toBe('Recovery Email')
            ->and($array['type'])->toBe('email')
            ->and($array['delay_minutes'])->toBe(45)
            ->and($array['parameters'])->toBe(['discount_percentage' => 20])
            ->and($array['priority'])->toBe(3)
            ->and($array['is_immediate'])->toBeFalse()
            ->and($array['has_discount'])->toBeTrue();
    });

    it('supports all strategy types', function (string $type): void {
        $strategy = new RecoveryStrategy('id', 'Name', $type, 0);

        expect($strategy->type)->toBe($type);
    })->with(['email', 'push', 'popup', 'sms']);
});
