<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventReferenceResolver;
use Illuminate\Database\Eloquent\Model;

final class NullEventReferenceResolver implements EventReferenceResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolve(Model $subject): array
    {
        return [];
    }
}
