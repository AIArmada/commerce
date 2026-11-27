<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\Engagement\Contracts\ShareUrlGenerator;
use Illuminate\Support\Str;

final class DefaultShareUrlGenerator implements ShareUrlGenerator
{
    public function generateShareUrl(mixed $shareable, array $options = []): string
    {
        $base = method_exists($shareable, 'shareUrl') ? $shareable->shareUrl() : url('/');
        $token = $options['token'] ?? Str::random(32);

        [$url, $fragment] = array_pad(explode('#', $base, 2), 2, null);
        [$path, $query] = array_pad(explode('?', $url, 2), 2, '');
        $parameters = [];

        foreach (explode('&', $query) as $parameter) {
            if ($parameter === '') {
                continue;
            }

            $key = urldecode(explode('=', $parameter, 2)[0]);

            if ($key === 'share' || str_starts_with($key, 'share[')) {
                continue;
            }

            $parameters[] = $parameter;
        }

        $parameters[] = 'share=' . rawurlencode((string) $token);

        return $path . '?' . implode('&', $parameters)
            . ($fragment === null ? '' : '#' . $fragment);
    }
}
