<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Support;

final class NormalizesSocialHandle
{
    public function normalize(?string $handle): ?string
    {
        if ($handle === null) {
            return null;
        }

        $handle = mb_trim($handle);

        if ($handle === '') {
            return null;
        }

        // Remove leading @
        if (str_starts_with($handle, '@')) {
            $handle = mb_substr($handle, 1);
        }

        return $handle !== '' ? $handle : null;
    }
}
