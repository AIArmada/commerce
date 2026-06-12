<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Events;

use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Foundation\Events\Dispatchable;

final class FeedbackResponseSubmitted
{
    use Dispatchable;

    public function __construct(
        public FeedbackResponse $response,
    ) {}
}
