<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventSearchIndexer
{
    public function index(mixed $target): void;

    public function remove(mixed $target): void;
}
