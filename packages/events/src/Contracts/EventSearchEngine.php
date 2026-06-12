<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventSearchEngine
{
    public function search(array $criteria): iterable;
}
