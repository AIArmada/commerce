<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventTranslationProvider
{
    public function translate(mixed $target, string $field, string $locale): ?string;
}
