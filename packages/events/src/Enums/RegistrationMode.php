<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum RegistrationMode: string
{
    use HasLabelOptions;

    case Required = 'required';
    case Optional = 'optional';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'Required',
            self::Optional => 'Optional',
            self::None => 'Open Door',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Required => 'primary',
            self::Optional => 'warning',
            self::None => 'gray',
        };
    }

    public function isRequired(): bool
    {
        return $this === self::Required;
    }

    public function isOpenDoor(): bool
    {
        return $this === self::None;
    }
}
