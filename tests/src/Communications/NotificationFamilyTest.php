<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\NotificationFamily;

beforeEach(function (): void {
    config()->set('communications.features.owner.enabled', false);
});

describe('NotificationFamily', function (): void {
    it('is string-backed', function (): void {
        expect(NotificationFamily::EventReminder->value)->toBeString();
    });

    it('has expected cases', function (): void {
        expect(NotificationFamily::EventReminder->value)->toBe('event_reminder');
        expect(NotificationFamily::EventUpdate->value)->toBe('event_update');
        expect(NotificationFamily::EventCancellation->value)->toBe('event_cancellation');
        expect(NotificationFamily::RegistrationConfirmation->value)->toBe('registration_confirmation');
        expect(NotificationFamily::SystemAnnouncement->value)->toBe('system_announcement');
        expect(NotificationFamily::SecurityAlert->value)->toBe('security_alert');
        expect(NotificationFamily::WelcomeMessage->value)->toBe('welcome_message');
        expect(NotificationFamily::AchievementUnlocked->value)->toBe('achievement_unlocked');
        expect(NotificationFamily::DigestDaily->value)->toBe('digest_daily');
    });

    it('provides non-empty labels for all cases', function (): void {
        foreach (NotificationFamily::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});
