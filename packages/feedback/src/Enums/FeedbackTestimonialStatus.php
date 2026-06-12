<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Enums;

use AIArmada\Feedback\Enums\Concerns\HasLabelOptions;

enum FeedbackTestimonialStatus: string
{
    use HasLabelOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Published = 'published';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Published => 'Published',
            self::Hidden => 'Hidden',
        };
    }
}
