<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventRegistrationQuestion;

final class EventRegistrationQuestionUpdated
{
    /** @param array<string, array{old: mixed, new: mixed}> $changes */
    public function __construct(
        public EventRegistrationQuestion $question,
        public array $changes = [],
    ) {}
}
