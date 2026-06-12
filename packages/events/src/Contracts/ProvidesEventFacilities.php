<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface ProvidesEventFacilities
{
    public function eventFacilities(): iterable;
}
