<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventTranslationProvider;

final class NullEventTranslationProvider implements EventTranslationProvider
{
    public function translate(mixed $target, string $field, string $locale): ?string
    {
        return null;
    }
}
