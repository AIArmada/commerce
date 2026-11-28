<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Actions;

use AIArmada\Persons\Models\Person;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

final readonly class PersistPerson
{
    public function execute(Person $person): void
    {
        try {
            $this->save($person);
        } catch (UniqueConstraintViolationException $exception) {
            // The savepoint has rolled back before checking the contested slug.
            $collision = $person->newQuery()->where('slug', $person->slug);

            if ($person->exists) {
                $collision->whereKeyNot($person->getKey());
            }

            if (! $collision->exists()) {
                throw $exception;
            }

            $person->slug = null;
            $this->save($person);
        }
    }

    private function save(Person $person): void
    {
        $person->getConnection()->transaction(function () use ($person): void {
            if (! $person->save()) {
                throw new RuntimeException('Person persistence was cancelled.');
            }
        });
    }
}
