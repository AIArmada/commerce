<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Queries;

use AIArmada\Events\Contracts\EventReferenceResolver;
use AIArmada\Events\Features\Catalog\Models\EventReference;

final class DefaultEventReferenceResolver implements EventReferenceResolver
{
    public function resolve(string $referenceId): array
    {
        $reference = EventReference::query()->whereKey($referenceId)->first();

        if (! $reference instanceof EventReference) {
            return [];
        }

        return [
            'id' => (string) $reference->getKey(),
            'title' => $reference->title,
            'citation' => $reference->citation,
            'url' => $reference->url,
            'reference_type' => $reference->reference_type,
        ];
    }
}
