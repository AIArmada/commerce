<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventRecurrenceRule;

interface EventOccurrenceGenerator
{
    public function generateFromRule(EventRecurrenceRule $rule, array $options = []): iterable;
}
