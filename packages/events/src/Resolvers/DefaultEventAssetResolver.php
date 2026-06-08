<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventAssetResolver;
use Illuminate\Database\Eloquent\Model;

final class DefaultEventAssetResolver implements EventAssetResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolve(Model $subject): array
    {
        $mediaReferences = $subject->getAttribute('media_references');

        return is_array($mediaReferences) ? $mediaReferences : [];
    }
}
