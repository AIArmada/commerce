<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Events;

use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Foundation\Events\Dispatchable;

final class FeedbackTestimonialHidden
{
    use Dispatchable;

    public function __construct(
        public FeedbackTestimonial $testimonial,
    ) {}
}
