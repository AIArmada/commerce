<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Events\Contracts\EventTranslationProvider;

final class NullEventTranslationDriver implements EventTranslationProvider
{
    public function translate(mixed $target, string $field, string $locale): ?string
    {
        return null;
    }
}
