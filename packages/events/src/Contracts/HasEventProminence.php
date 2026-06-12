<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface HasEventProminence
{
    public function defaultEventProminenceFor(string $roleCode): string;
}
