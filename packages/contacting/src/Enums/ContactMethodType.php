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
}
