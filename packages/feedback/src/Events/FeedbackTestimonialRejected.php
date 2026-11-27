<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class FeedbackTestimonialRejected
{
    use Dispatchable;

    public function __construct(
        public string $testimonialId,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}
}
