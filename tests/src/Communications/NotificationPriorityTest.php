<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\NotificationPriority;

beforeEach(function (): void {
    config()->set('communications.features.owner.enabled', false);
});

describe('NotificationPriority', function (): void {
    it('has 4 cases', function (): void {
        expect(NotificationPriority::cases())->toHaveCount(4);
    });

    it('has expected values', function (): void {
        expect(NotificationPriority::Low->value)->toBe('low');
        expect(NotificationPriority::Normal->value)->toBe('normal');
        expect(NotificationPriority::High->value)->toBe('high');
        expect(NotificationPriority::Urgent->value)->toBe('urgent');
    });

    it('provides non-empty labels for all cases', function (): void {
        foreach (NotificationPriority::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });

    it('provides expected colors for all cases', function (): void {
        expect(NotificationPriority::Low->color())->toBe('gray');
        expect(NotificationPriority::Normal->color())->toBe('primary');
        expect(NotificationPriority::High->color())->toBe('warning');
        expect(NotificationPriority::Urgent->color())->toBe('danger');
    });
});
