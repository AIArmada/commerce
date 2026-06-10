<?php

declare(strict_types=1);

namespace AIArmada\Growth\Enums;

enum ExperimentStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Concluded = 'concluded';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Concluded => 'Concluded',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Paused => 'warning',
            self::Concluded => 'primary',
            self::Archived => 'gray',
        };
    }
}
