<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramReferenceReader
{
    public function exists(string $id): bool;

    /** @return array<string, string> */
    public function options(string $search = '', int $limit = 50): array;

    public function label(string $id): ?string;
}
