<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Support\EventRegistrationScope;
use Illuminate\Database\Eloquent\Model;

interface EventRegistrationScopeResolver
{
    public function resolve(Model $target): EventRegistrationScope;
}
