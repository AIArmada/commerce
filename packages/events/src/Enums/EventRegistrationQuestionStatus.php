<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum EventRegistrationQuestionStatus: string
{
    use HasLabelOptions;

    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }
}
