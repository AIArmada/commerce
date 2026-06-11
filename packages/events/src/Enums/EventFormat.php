<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum EventFormat: string
{
    use HasLabelOptions;

    case Physical = 'physical';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Physical',
            self::Online => 'Online',
            self::Hybrid => 'Hybrid',
        };
    }

    public function requiresAddress(): bool
    {
        return $this === self::Physical || $this === self::Hybrid;
    }

    public function requiresLivestream(): bool
    {
        return $this === self::Online || $this === self::Hybrid;
    }
}
