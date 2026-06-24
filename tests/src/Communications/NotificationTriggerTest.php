<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\NotificationTrigger;

beforeEach(function (): void {
    config()->set('communications.features.owner.enabled', false);
});

describe('NotificationTrigger', function (): void {
    it('is string-backed', function (): void {
        expect(NotificationTrigger::EventPublished->value)->toBeString();
    });

    it('has expected cases', function (): void {
        expect(NotificationTrigger::EventPublished->value)->toBe('event_published');
        expect(NotificationTrigger::EventCancelled->value)->toBe('event_cancelled');
        expect(NotificationTrigger::RegistrationConfirmed->value)->toBe('registration_confirmed');
        expect(NotificationTrigger::PaymentCompleted->value)->toBe('payment_completed');
        expect(NotificationTrigger::AccountCreated->value)->toBe('account_created');
        expect(NotificationTrigger::SystemAlert->value)->toBe('system_alert');
        expect(NotificationTrigger::ScheduledDispatch->value)->toBe('scheduled_dispatch');
        expect(NotificationTrigger::ManualDispatch->value)->toBe('manual_dispatch');
    });

    it('provides non-empty labels for all cases', function (): void {
        foreach (NotificationTrigger::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});
