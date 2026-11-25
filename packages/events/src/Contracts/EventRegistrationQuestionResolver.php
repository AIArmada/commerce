<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventRegistrationQuestionData;

interface EventRegistrationQuestionResolver
{
    /**
     * @return list<EventRegistrationQuestionData>
     */
    public function resolve(string $targetType, string $targetId): array;
}
