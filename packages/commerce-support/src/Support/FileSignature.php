<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

final class FileSignature
{
    public static function isPdf(?string $content): bool
    {
        return is_string($content) && str_starts_with($content, '%PDF-');
    }

    public static function isPng(?string $content): bool
    {
        return is_string($content) && str_starts_with($content, "\x89PNG\r\n\x1a\n");
    }
}
