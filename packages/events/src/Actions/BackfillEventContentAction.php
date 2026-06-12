<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Event;

final class BackfillEventContentAction
{
    public function handle(Event $event, array $options = []): void
    {
        app(SynchronizeEventContent::class)->handle($event, $options);
    }

    public function asJob(Event $event, array $options = []): void
    {
        $this->handle($event, $options);
    }
}
