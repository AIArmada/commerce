<?php

declare(strict_types=1);

namespace AIArmada\Links\Contracts;

interface BotDetectorInterface
{
    public function isBot(?string $userAgent): bool;
}
