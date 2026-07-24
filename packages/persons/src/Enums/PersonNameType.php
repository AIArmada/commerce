<?php

declare(strict_types=1);

namespace AIArmada\Persons\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum PersonNameType: string
{
    use HasLabelOptions;

    case Legal = 'legal';
    case Display = 'display';
    case Birth = 'birth';
    case Religious = 'religious';
    case Professional = 'professional';
    case Previous = 'previous';

    public function label(): string
    {
        return match ($this) {
            self::Legal => 'Legal',
            self::Display => 'Display',
            self::Birth => 'Birth',
            self::Religious => 'Religious',
            self::Professional => 'Professional',
            self::Previous => 'Previous',
        };
    }
}
