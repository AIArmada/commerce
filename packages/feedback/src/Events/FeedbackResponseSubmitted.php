<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class FeedbackResponseSubmitted
{
    use Dispatchable;

    public function __construct(
        public string $responseId,
        public string $formId,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}
}
