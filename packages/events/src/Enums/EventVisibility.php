<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum EventVisibility: string
{
    use HasLabelOptions;

    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';
    case RegisteredOnly = 'registered_only';
    case AttendeesOnly = 'attendees_only';
    case ManagersOnly = 'managers_only';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Unlisted => 'Unlisted',
            self::Private => 'Private',
            self::RegisteredOnly => 'Registered Only',
            self::AttendeesOnly => 'Attendees Only',
            self::ManagersOnly => 'Managers Only',
            self::Internal => 'Internal',
        };
    }
}
