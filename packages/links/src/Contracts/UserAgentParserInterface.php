<?php

declare(strict_types=1);

namespace AIArmada\Links\Contracts;

interface UserAgentParserInterface
{
    /**
     * @return array{
     *     device_type: string|null,
     *     device_brand: string|null,
     *     device_model: string|null,
     *     browser: string|null,
     *     browser_version: string|null,
     *     os: string|null,
     *     os_version: string|null,
     *     is_bot: bool,
     * }
     */
    public function parse(?string $userAgent): array;
}
