<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class FeedbackInvitationSent
{
    use Dispatchable;

    public function __construct(
        public string $invitationId,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}
}
