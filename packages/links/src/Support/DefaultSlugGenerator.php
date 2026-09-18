<?php

declare(strict_types=1);

namespace AIArmada\Links\Support;

use AIArmada\Links\Contracts\SlugGeneratorInterface;

final class DefaultSlugGenerator implements SlugGeneratorInterface
{
    public function generate(int $length): string
    {
        $alphabet = (string) config('links.defaults.slug_alphabet', 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789');
        $max = mb_strlen($alphabet) - 1;
        $slug = '';

        for ($i = 0; $i < max(1, $length); $i++) {
            $slug .= $alphabet[random_int(0, $max)];
        }

        return $slug;
    }
}
