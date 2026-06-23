<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class PendingReview extends EventStatus
{
    protected static string $name = 'pending_review';

    public static function name(): string
    {
        return 'pending_review';
    }

    public function label(): string
    {
        return 'Pending Review';
    }
}
