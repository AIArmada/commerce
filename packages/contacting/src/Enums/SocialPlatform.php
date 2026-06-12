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
}
