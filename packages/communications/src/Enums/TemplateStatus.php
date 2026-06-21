<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum TemplateStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Disabled => 'Disabled',
        };
    }
}
