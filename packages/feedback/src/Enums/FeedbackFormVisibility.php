<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Enums;

use AIArmada\Feedback\Enums\Concerns\HasLabelOptions;

enum FeedbackFormVisibility: string
{
    use HasLabelOptions;

    case Private = 'private';
    case Public = 'public';
    case InviteOnly = 'invite_only';
    case Embedded = 'embedded';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Public => 'Public',
            self::InviteOnly => 'Invite Only',
            self::Embedded => 'Embedded',
        };
    }
}
