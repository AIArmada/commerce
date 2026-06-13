<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Enums;

enum ContactMethodType: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Mobile = 'mobile';
    case Whatsapp = 'whatsapp';
    case Website = 'website';
    case Telegram = 'telegram';
    case Fax = 'fax';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Phone => 'Phone',
            self::Mobile => 'Mobile',
            self::Whatsapp => 'WhatsApp',
            self::Website => 'Website',
            self::Telegram => 'Telegram',
            self::Fax => 'Fax',
            self::Other => 'Other',
        };
    }

    /**
     * @param  array<int, string>|array<string, string>|null  $allowedTypes
     * @return array<string, string>
     */
    public static function options(?array $allowedTypes = null): array
    {
        $allowedTypes ??= array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );

        $options = [];

        foreach ($allowedTypes as $key => $value) {
            if (! is_int($key)) {
                $options[(string) $key] = (string) $value;

                continue;
            }

            $type = (string) $value;

            $options[$type] = self::tryFrom($type)?->label() ?? self::formatLabel($type);
        }

        return $options;
    }

    private static function formatLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
