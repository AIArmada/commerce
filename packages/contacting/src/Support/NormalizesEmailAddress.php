<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Support;

final class NormalizesEmailAddress
{
    public function normalize(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = mb_trim($email);

        if ($email === '') {
            return null;
        }

        $email = mb_strtolower($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
