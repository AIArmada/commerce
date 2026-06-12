<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventSearchIndexer;

final class NullEventSearchIndexer implements EventSearchIndexer
{
    public function index(mixed $target): void
    {
        // no-op
    }

    public function remove(mixed $target): void
    {
        // no-op
    }
}
