<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('cart_recovery.recoveryEnabled', true);
        $this->migrator->add('cart_recovery.defaultAbandonmentThresholdMinutes', 60);
        $this->migrator->add('cart_recovery.maxRecoveryAttempts', 3);
        $this->migrator->add('cart_recovery.cooldownBetweenAttemptsHours', 24);

        $this->migrator->add('cart_recovery.emailEnabled', true);
        $this->migrator->add('cart_recovery.emailFromName', (string) config('app.name'));
        $this->migrator->add('cart_recovery.emailFromAddress', (string) config('mail.from.address'));
        $this->migrator->add('cart_recovery.emailReplyTo', null);
        $this->migrator->add('cart_recovery.emailTrackOpens', true);
        $this->migrator->add('cart_recovery.emailTrackClicks', true);

        $this->migrator->add('cart_recovery.smsEnabled', false);
        $this->migrator->add('cart_recovery.smsProvider', null);
        $this->migrator->add('cart_recovery.smsFromNumber', null);
        $this->migrator->add('cart_recovery.smsMaxLength', 160);

        $this->migrator->add('cart_recovery.pushEnabled', false);
        $this->migrator->add('cart_recovery.pushProvider', null);
        $this->migrator->add('cart_recovery.pushIconUrl', null);
        $this->migrator->add('cart_recovery.pushRequireInteraction', false);

        $this->migrator->add('cart_recovery.sendStartHour', 9);
        $this->migrator->add('cart_recovery.sendEndHour', 21);
        $this->migrator->add('cart_recovery.respectUserTimezone', true);
        $this->migrator->add('cart_recovery.blockedDays', []);

        $this->migrator->add('cart_recovery.minCartValue', 0);
        $this->migrator->add('cart_recovery.maxMessagesPerCustomerPerWeek', 3);
        $this->migrator->add('cart_recovery.excludeRepeatRecoveries', true);
        $this->migrator->add('cart_recovery.excludeIfOrderedWithinDays', 7);
        $this->migrator->add('cart_recovery.customExclusionRules', []);
    }
};
