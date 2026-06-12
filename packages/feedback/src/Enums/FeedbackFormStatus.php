<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Enums;

use AIArmada\Feedback\Enums\Concerns\HasLabelOptions;

enum FeedbackFormStatus: string
{
    use HasLabelOptions;

    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
        };
    }
}
