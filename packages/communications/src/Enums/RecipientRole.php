<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum RecipientRole: string
{
    case To = 'to';
    case Cc = 'cc';
    case Bcc = 'bcc';
    case Sender = 'sender';
    case ReplyTo = 'reply_to';

    public function label(): string
    {
        return match ($this) {
            self::To => 'To',
            self::Cc => 'CC',
            self::Bcc => 'BCC',
            self::Sender => 'Sender',
            self::ReplyTo => 'Reply-To',
        };
    }
}
