<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventImportScopeValidator
{
    public function validate(mixed $eventId, mixed $occurrenceId, mixed $actor, string $ability): void;
}
