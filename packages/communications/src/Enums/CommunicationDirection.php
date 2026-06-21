<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum CommunicationDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'Outbound',
            self::Inbound => 'Inbound',
            self::Internal => 'Internal',
        };
    }
}
