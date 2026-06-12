<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum EventFormat: string
{
    case InPerson = 'in_person';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'In Person',
            self::Online => 'Online',
            self::Hybrid => 'Hybrid',
        };
    }
}
