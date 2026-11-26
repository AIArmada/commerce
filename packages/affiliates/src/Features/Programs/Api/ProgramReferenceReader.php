<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramReferenceReader
{
    public function exists(string $id): bool;

    public function assertReadable(string $id): void;

    public function assertSharedConnection(int $connectionIdentity): void;

    public function assertWritable(string $id, int $connectionIdentity): void;

    public function lockForAdministration(string $id, int $connectionIdentity): void;

    /** @return array<string, string> */
    public function options(string $search = '', int $limit = 50): array;

    public function label(string $id): ?string;
}
