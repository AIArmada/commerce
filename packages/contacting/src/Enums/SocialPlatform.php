<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Enums;

enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Tiktok = 'tiktok';
    case Youtube = 'youtube';
    case Linkedin = 'linkedin';
    case X = 'x';
    case Threads = 'threads';
    case Telegram = 'telegram';
    case TelegramChannel = 'telegram_channel';
    case TelegramGroup = 'telegram_group';
    case WhatsappChannel = 'whatsapp_channel';
    case Website = 'website';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Tiktok => 'TikTok',
            self::Youtube => 'YouTube',
            self::Linkedin => 'LinkedIn',
            self::X => 'X / Twitter',
            self::Threads => 'Threads',
            self::Telegram => 'Telegram',
            self::TelegramChannel => 'Telegram Channel',
            self::TelegramGroup => 'Telegram Group',
            self::WhatsappChannel => 'WhatsApp Channel',
            self::Website => 'Website',
            self::Other => 'Other',
        };
    }

    /**
     * @param  array<int, string>|array<string, string>|null  $allowedPlatforms
     * @return array<string, string>
     */
    public static function options(?array $allowedPlatforms = null): array
    {
        $allowedPlatforms ??= array_map(
            static fn (self $platform): string => $platform->value,
            self::cases(),
        );

        $options = [];

        foreach ($allowedPlatforms as $key => $value) {
            if (! is_int($key)) {
                $options[(string) $key] = (string) $value;

                continue;
            }

            $platform = (string) $value;

            $options[$platform] = self::tryFrom($platform)?->label() ?? self::formatLabel($platform);
        }

        return $options;
    }

    private static function formatLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
