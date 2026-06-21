<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface PayloadRedactor
{
    public function redact(array $payload): array;

    public function redactRequest(array $request): array;

    public function redactResponse(array $response): array;
}
