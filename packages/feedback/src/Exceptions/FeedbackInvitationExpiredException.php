<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Exceptions;

use RuntimeException;

final class FeedbackInvitationExpiredException extends RuntimeException
{
    public function __construct(
        public readonly string $invitationId,
        string $message = 'This invitation has expired.',
    ) {
        parent::__construct($message);
    }
}
