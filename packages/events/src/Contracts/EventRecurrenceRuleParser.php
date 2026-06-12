<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventRecurrenceRuleData;

interface EventRecurrenceRuleParser
{
    public function parse(array | string $input): EventRecurrenceRuleData;
}
