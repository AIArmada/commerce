<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Settings;

use AIArmada\FilamentCart\Settings\Casts\ScalarArrayCast;
use Spatie\LaravelSettings\Settings;

/**
 * Settings for cart recovery behavior and messaging.
 */
class CartRecoverySettings extends Settings
{
    public bool $recoveryEnabled;

    public int $defaultAbandonmentThresholdMinutes;

    public int $maxRecoveryAttempts;

    public int $cooldownBetweenAttemptsHours;

    public bool $emailEnabled;

    public string $emailFromName;

    public string $emailFromAddress;

    public ?string $emailReplyTo;

    public bool $emailTrackOpens;

    public bool $emailTrackClicks;

    public bool $smsEnabled;

    public ?string $smsProvider;

    public ?string $smsFromNumber;

    public int $smsMaxLength;

    public bool $pushEnabled;

    public ?string $pushProvider;

    public ?string $pushIconUrl;

    public bool $pushRequireInteraction;

    public int $sendStartHour;

    public int $sendEndHour;

    public bool $respectUserTimezone;

    /** @var array<int, array<string, string>> */
    public array $blockedDays;

    public int $minCartValue;

    public int $maxMessagesPerCustomerPerWeek;

    public bool $excludeRepeatRecoveries;

    public int $excludeIfOrderedWithinDays;

    /** @var array<string, string> */
    public array $customExclusionRules;

    public static function group(): string
    {
        return 'cart_recovery';
    }

    /**
     * @return array<string, class-string>
     */
    public static function casts(): array
    {
        return [
            'blockedDays' => ScalarArrayCast::class,
            'customExclusionRules' => ScalarArrayCast::class,
        ];
    }
}
