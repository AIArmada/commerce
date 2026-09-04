<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventRegistrationQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface EventRegistrationQuestionResolver
{
    /**
     * Resolve active questions for an event, occurrence, or session.
     *
     * More specific definitions replace an event-level definition with the same
     * field key. The returned collection is ordered for presentation.
     *
     * @return Collection<int, EventRegistrationQuestion>
     */
    public function resolve(Model $target): Collection;
}
