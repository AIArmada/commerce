<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventReferenceResolver;
use AIArmada\Events\Models\EventReference;

final class DefaultEventReferenceResolver implements EventReferenceResolver
{
    public function resolve(EventReference $reference): array
    {
        return [
            'id' => $reference->id,
            'title' => $reference->title,
            'citation' => $reference->citation,
            'url' => $reference->url,
            'reference_type' => $reference->reference_type,
        ];
    }
}
