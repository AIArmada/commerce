<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;
use Illuminate\Database\Eloquent\Model;

interface EventLifecycleWorkflow
{
    public function postpone(Event $event, ?Model $actor = null, ?string $note = null): Event;

    public function delay(Event $event, ?Model $actor = null, ?string $note = null): Event;

    public function resume(Event $event, ?Model $actor = null, ?string $note = null): Event;

    public function cancel(Event $event, ?Model $actor = null, ?string $note = null, ?string $reason = null): Event;
}
