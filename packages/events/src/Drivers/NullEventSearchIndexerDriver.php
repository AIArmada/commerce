<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Events\Contracts\EventSearchIndexer;

final class NullEventSearchIndexerDriver implements EventSearchIndexer
{
    public function index(mixed $target): void {}

    public function remove(mixed $target): void {}
}
