<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Spatie\LaravelData\Data;

final class EventScheduleValidationResult extends Data
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $conflicts = [],
        public readonly ?string $message = null,
    ) {}
}
