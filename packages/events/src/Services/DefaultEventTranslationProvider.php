<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Traits\HasCommerceTranslations;
use AIArmada\Events\Contracts\EventTranslationProvider;

final class DefaultEventTranslationProvider implements EventTranslationProvider
{
    public function translate(mixed $target, string $field, string $locale): ?string
    {
        if (! method_exists($target, 'translate')) {
            return null;
        }

        /** @see HasCommerceTranslations::translate() */
        return $target->translate($field, $locale);
    }
}
