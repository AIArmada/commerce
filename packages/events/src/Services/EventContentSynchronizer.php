<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;

final class EventContentSynchronizer
{
    public function __construct(
        private readonly EventContentNormalizer $normalizer,
    ) {}

    public function sync(Event $event, array $options = []): void
    {
        $content = array_merge(
            $event->only(['title', 'summary', 'description']),
            $options,
        );

        $normalized = $this->normalizer->normalize($content);

        $event->update($normalized);
    }
}
