<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramReferenceReader
{
    public function exists(string $id): bool;

    public function existsForOwner(string $id, ?string $ownerType, string | int | null $ownerId, int $connectionIdentity, bool $includeGlobal = false): bool;

    public function assertReadable(string $id): void;

    public function assertSharedConnection(int $connectionIdentity): void;

    public function assertWritable(string $id, int $connectionIdentity): void;

    public function lockForAdministration(string $id, int $connectionIdentity): void;

    /** @return array<string, string> */
    public function options(string $search = '', int $limit = 50, ?bool $includeGlobal = null): array;

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public function labels(array $ids, ?bool $includeGlobal = null): array;

    public function label(string $id, ?bool $includeGlobal = null): ?string;
}
