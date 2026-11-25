<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Actions;

use InvalidArgumentException;

final class TicketInput
{
    public static function validate(string $subject, string $category, string $priority, string $status): void
    {
        if (mb_trim($subject) === '' || mb_strlen($subject) > 255
            || ! in_array($category, ['general', 'billing', 'technical', 'tax', 'account'], true)
            || ! in_array($priority, ['low', 'medium', 'high', 'urgent'], true)
            || ! in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
            throw new InvalidArgumentException('Invalid support ticket details.');
        }
    }

    public static function message(string $message): void
    {
        if (mb_trim($message) === '' || mb_strlen($message) > 5000) {
            throw new InvalidArgumentException('Support message must contain 1–5000 characters.');
        }
    }
}
