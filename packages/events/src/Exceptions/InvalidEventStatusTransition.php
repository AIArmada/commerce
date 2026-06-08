<?php

declare(strict_types=1);

namespace AIArmada\Events\Exceptions;

use AIArmada\Events\Enums\EventStatus;
use InvalidArgumentException;

final class InvalidEventStatusTransition extends InvalidArgumentException
{
    public static function from(EventStatus $from, EventStatus $to): self
    {
        return new self(sprintf(
            'The [%s] event status cannot transition to [%s].',
            $from->value,
            $to->value,
        ));
    }
}
