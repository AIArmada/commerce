<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use Illuminate\Database\Eloquent\Model;

interface EventDisplayTimezoneResolver
{
    public function resolve(Model $record, ?Model $viewer = null): string;
}
