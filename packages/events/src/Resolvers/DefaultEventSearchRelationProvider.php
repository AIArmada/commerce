<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventSearchRelationProvider;

final class DefaultEventSearchRelationProvider implements EventSearchRelationProvider
{
    /**
     * @return array<int|string, mixed>
     */
    public function relations(): array
    {
        return [
            'classifications.term',
            'timeExpressions',
        ];
    }
}
