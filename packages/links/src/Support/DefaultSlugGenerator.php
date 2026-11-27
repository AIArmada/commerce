<?php

declare(strict_types=1);

namespace AIArmada\Links\Support;

use AIArmada\Links\Contracts\SlugGeneratorInterface;
use InvalidArgumentException;

final class DefaultSlugGenerator implements SlugGeneratorInterface
{
    public function generate(int $length): string
    {
        $alphabet = (string) config('links.defaults.slug_alphabet', 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789');
        if ($length < 1 || $length > 100 || preg_match('/\A[A-Za-z0-9_-]+\z/', $alphabet) !== 1 || count(array_unique(mb_str_split($alphabet))) < 2) {
            throw new InvalidArgumentException('Invalid link slug length or alphabet.');
        }

        $max = mb_strlen($alphabet) - 1;
        $slug = '';

        for ($i = 0; $i < $length; $i++) {
            $slug .= $alphabet[random_int(0, $max)];
        }

        return $slug;
    }
}
