<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Events;

use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use Illuminate\Foundation\Events\Dispatchable;

final class FeedbackFormCreated
{
    use Dispatchable;

    public function __construct(
        public FeedbackForm $form,
    ) {}
}
