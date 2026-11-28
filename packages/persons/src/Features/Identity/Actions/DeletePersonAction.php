<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Actions;

use AIArmada\Persons\Models\Person;
use RuntimeException;

final readonly class DeletePersonAction
{
    public function execute(Person $person): bool
    {
        return $person->getConnection()->transaction(function () use ($person): bool {
            $locked = $person->newQuery()->whereKey($person->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->delete()) {
                throw new RuntimeException('Person deletion was cancelled.');
            }

            return true;
        });
    }
}
