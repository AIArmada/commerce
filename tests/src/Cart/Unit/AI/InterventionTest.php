<?php

declare(strict_types=1);

use AIArmada\Cart\AI\Intervention;

describe('Intervention', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $intervention = new Intervention(
            type: 'email',
            priority: 1,
            message: 'Send recovery email'
        );

        expect($intervention->type)->toBe('email')
            ->and($intervention->priority)->toBe(1)
            ->and($intervention->message)->toBe('Send recovery email')
            ->and($intervention->parameters)->toBeEmpty();
    });

    it('can be instantiated with optional parameters', function (): void {
        $intervention = new Intervention(
            type: 'discount',
            priority: 2,
            message: 'Offer 10% discount',
            parameters: ['discount_percentage' => 10, 'delay_minutes' => 30]
        );

        expect($intervention->parameters)->toBe(['discount_percentage' => 10, 'delay_minutes' => 30]);
    });

    it('is immediate when delay_minutes is zero', function (): void {
        $intervention = new Intervention('exit_intent', 1, 'Show exit popup', ['delay_minutes' => 0]);

        expect($intervention->isImmediate())->toBeTrue();
    });

    it('is immediate when delay_minutes is not set', function (): void {
        $intervention = new Intervention('exit_intent', 1, 'Show exit popup');

        expect($intervention->isImmediate())->toBeTrue();
    });

    it('is not immediate when delay_minutes is positive', function (): void {
        $intervention = new Intervention('email', 1, 'Send recovery email', ['delay_minutes' => 30]);

        expect($intervention->isImmediate())->toBeFalse();
    });

    it('gets zero delay minutes when not set', function (): void {
        $intervention = new Intervention('email', 1, 'Send email');

        expect($intervention->getDelayMinutes())->toBe(0);
    });

    it('gets correct delay minutes when set', function (): void {
        $intervention = new Intervention('email', 1, 'Send email', ['delay_minutes' => 60]);

        expect($intervention->getDelayMinutes())->toBe(60);
    });

    it('converts to array correctly', function (): void {
        $intervention = new Intervention(
            type: 'push_notification',
            priority: 2,
            message: 'Send push notification',
            parameters: ['title' => 'Your cart is waiting', 'delay_minutes' => 15]
        );

        $array = $intervention->toArray();

        expect($array)->toBeArray()
            ->and($array['type'])->toBe('push_notification')
            ->and($array['priority'])->toBe(2)
            ->and($array['message'])->toBe('Send push notification')
            ->and($array['parameters'])->toBe(['title' => 'Your cart is waiting', 'delay_minutes' => 15])
            ->and($array['is_immediate'])->toBeFalse();
    });

    it('shows is_immediate as true in array when immediate', function (): void {
        $intervention = new Intervention('exit_intent', 1, 'Show popup');

        $array = $intervention->toArray();

        expect($array['is_immediate'])->toBeTrue();
    });

    it('supports all intervention types', function (string $type): void {
        $intervention = new Intervention($type, 1, 'Test intervention');

        expect($intervention->type)->toBe($type);
    })->with(['email', 'discount', 'push_notification', 'exit_intent', 'sms']);
});
