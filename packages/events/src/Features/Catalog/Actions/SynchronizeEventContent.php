<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Platform\EventContentNormalizer;
use AIArmada\Events\Platform\EventWriteGuard;
use Lorisleiva\Actions\Concerns\AsAction;

final class SynchronizeEventContent
{
    use AsAction;

    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(Event $event, array $options = []): void
    {
        EventWriteGuard::findOrFail($event->getKey());

        $content = array_merge(
            $event->only(['title', 'summary', 'description']),
            $options,
        );

        $event->update($this->contentNormalizer->normalize($content));
    }
}
