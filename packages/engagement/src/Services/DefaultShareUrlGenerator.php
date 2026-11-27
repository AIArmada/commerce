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
        $separator = str_contains($url, '?')
            ? (str_ends_with($url, '?') || str_ends_with($url, '&') ? '' : '&')
            : '?';

        return $url . $separator . 'share=' . rawurlencode((string) $token)
            . ($fragment === null ? '' : '#' . $fragment);
    }
}
