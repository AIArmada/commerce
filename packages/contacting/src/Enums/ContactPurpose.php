<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Enums;

enum ContactPurpose: string
{
    case General = 'general';
    case Admin = 'admin';
    case Support = 'support';
    case Billing = 'billing';
    case Sales = 'sales';
    case Registration = 'registration';
    case Media = 'media';
    case Donation = 'donation';
    case Partnership = 'partnership';
    case Emergency = 'emergency';
    case Privacy = 'privacy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Admin => 'Admin',
            self::Support => 'Support',
            self::Billing => 'Billing',
            self::Sales => 'Sales',
            self::Registration => 'Registration',
            self::Media => 'Media',
            self::Donation => 'Donation',
            self::Partnership => 'Partnership',
            self::Emergency => 'Emergency',
            self::Privacy => 'Privacy',
            self::Other => 'Other',
        };
    }

    /**
     * @param  array<int, string>|array<string, string>|null  $allowedPurposes
     * @return array<string, string>
     */
    public static function options(?array $allowedPurposes = null): array
    {
        $allowedPurposes ??= array_map(
            static fn (self $purpose): string => $purpose->value,
            self::cases(),
        );

        $options = [];

        foreach ($allowedPurposes as $key => $value) {
            if (! is_int($key)) {
                $options[(string) $key] = (string) $value;

                continue;
            }

            $purpose = (string) $value;

            $options[$purpose] = self::tryFrom($purpose)?->label() ?? self::formatLabel($purpose);
        }

        return $options;
    }

    private static function formatLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
