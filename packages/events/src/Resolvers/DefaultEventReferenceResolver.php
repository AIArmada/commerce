<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventReferenceResolver;
use Illuminate\Database\Eloquent\Model;

final class DefaultEventReferenceResolver implements EventReferenceResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolve(Model $subject): array
    {
        foreach (['references', 'reference_materials', 'source_materials'] as $attribute) {
            $references = $subject->getAttribute($attribute);

            if (is_array($references)) {
                return $references;
            }
        }

        return [];
    }
}
