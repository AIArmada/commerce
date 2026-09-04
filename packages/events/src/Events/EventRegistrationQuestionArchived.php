<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventRegistrationQuestion;

final class EventRegistrationQuestionArchived
{
    public function __construct(
        public EventRegistrationQuestion $question,
    ) {}
}
