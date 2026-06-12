<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Services\EventContentSynchronizer;
use Lorisleiva\Actions\Concerns\AsAction;

final class SynchronizeEventContent
{
    use AsAction;

    public function __construct(
        private readonly EventContentSynchronizer $synchronizer,
    ) {}

    public function handle(Event $event, array $options = []): void
    {
        $this->synchronizer->sync($event, $options);
    }
}
