<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface ConversionBeneficiaryReader
{
    public function find(string $id, int $connectionIdentity, bool $includeGlobal = false): ?ConversionBeneficiary;

    public function findByCode(string $code, int $connectionIdentity): ?ConversionBeneficiary;

    /**
     * @param list<string> $ids
     * @return array<string, ConversionBeneficiary>
     */
    public function lock(array $ids, int $connectionIdentity, bool $includeGlobal = false): array;
}
