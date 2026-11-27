<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Contracts;

interface CarrierTransport
{
    /**
     * @param array<string, mixed> $bizContent
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $bizContent): array;
}
