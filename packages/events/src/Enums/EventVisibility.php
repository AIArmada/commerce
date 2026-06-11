<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum EventVisibility: string
{
    use HasLabelOptions;

    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Unlisted => 'Unlisted',
            self::Private => 'Private',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Unlisted => 'warning',
            self::Private => 'gray',
        };
    }

    public function isPubliclyAccessible(): bool
    {
        return $this !== self::Private;
    }

    public function isDiscoverable(): bool
    {
        return $this === self::Public;
    }
}
