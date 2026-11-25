<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Queries;

use AIArmada\CommerceSupport\Traits\HasCommerceTranslations;
use AIArmada\Events\Contracts\EventTranslationProvider;

final class DefaultEventTranslationProvider implements EventTranslationProvider
{
    public function translate(mixed $target, string $field, string $locale): ?string
    {
        if (! is_object($target) || ! method_exists($target, 'translate')) {
            return null;
        }

        /** @see HasCommerceTranslations::translate() */
        $result = $target->translate($field, $locale);

        return is_string($result) ? $result : null;
    }
}
