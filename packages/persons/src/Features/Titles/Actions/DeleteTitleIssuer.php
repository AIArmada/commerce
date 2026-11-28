<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Actions;

use AIArmada\Persons\Features\Titles\Models\TitleAssignment;
use AIArmada\Persons\Features\Titles\Models\TitleIssuer;
use InvalidArgumentException;
use RuntimeException;

final readonly class DeleteTitleIssuer
{
    public function execute(TitleIssuer $issuer): bool
    {
        if (! $issuer->exists || $issuer->getKey() === null) {
            throw new InvalidArgumentException('Title issuer must be persisted.');
        }

        return $issuer->getConnection()->transaction(function () use ($issuer): bool {
            $locked = TitleIssuer::query()->whereKey($issuer->getKey())->lockForUpdate()->firstOrFail();

            $this->clearAssignments($locked);

            if (! $locked->delete()) {
                throw new RuntimeException('Title issuer deletion was cancelled.');
            }

            return true;
        });
    }

    public function clearAssignments(TitleIssuer $issuer): void
    {
        TitleAssignment::query()
            ->where('issuer_id', $issuer->getKey())
            ->update(['issuer_id' => null]);
    }
}
