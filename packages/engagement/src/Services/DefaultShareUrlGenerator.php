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
        $token = $options['token'] ?? Str::random(16);

        return $base . (str_contains($base, '?') ? '&' : '?') . 'share=' . $token;
    }
}
