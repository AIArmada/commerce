<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum EventStructure: string
{
    use HasLabelOptions;

    case Standalone = 'standalone';
    case Program = 'program';
    case Session = 'session';
    case Template = 'template';

    public function label(): string
    {
        return match ($this) {
            self::Standalone => 'Standalone',
            self::Program => 'Program',
            self::Session => 'Session',
            self::Template => 'Template',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Standalone => 'primary',
            self::Program => 'info',
            self::Session => 'success',
            self::Template => 'gray',
        };
    }

    public function isStandalone(): bool
    {
        return $this === self::Standalone;
    }

    public function isProgram(): bool
    {
        return $this === self::Program;
    }

    public function isSession(): bool
    {
        return $this === self::Session;
    }

    public function isTemplate(): bool
    {
        return $this === self::Template;
    }
}
