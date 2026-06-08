<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventClassificationResolver;
use Illuminate\Database\Eloquent\Model;

final class DefaultEventClassificationResolver implements EventClassificationResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolve(Model $subject): array
    {
        $taxonomy = $subject->getAttribute('taxonomy');

        return is_array($taxonomy) ? $taxonomy : [];
    }
}
