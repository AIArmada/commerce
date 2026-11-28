<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Affiliations\Actions;

use AIArmada\Persons\Features\Affiliations\Models\Affiliation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

final readonly class SaveAffiliation
{
    /** @param array<string, mixed> $attributes */
    public function create(Model $subject, array $attributes): Affiliation
    {
        $affiliation = new Affiliation;

        return $affiliation->getConnection()->transaction(function () use ($subject, $attributes, $affiliation): Affiliation {
            $locked = $this->lockSubject($subject);
            $affiliation->fill($this->attributes($attributes));
            $affiliation->affiliatable()->associate($locked);
            $this->persist($affiliation);

            return $affiliation;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Model $subject, Affiliation $affiliation, array $attributes): Affiliation
    {
        return $affiliation->getConnection()->transaction(function () use ($subject, $affiliation, $attributes): Affiliation {
            $locked = $this->lockAffiliation($this->lockSubject($subject), $affiliation);
            $locked->fill($this->attributes($attributes));
            $this->persist($locked);

            return $locked;
        });
    }

    public function delete(Model $subject, Affiliation $affiliation): bool
    {
        return $affiliation->getConnection()->transaction(function () use ($subject, $affiliation): bool {
            if (! $this->lockAffiliation($this->lockSubject($subject), $affiliation)->delete()) {
                throw new RuntimeException('Affiliation deletion was cancelled.');
            }

            return true;
        });
    }

    private function lockSubject(Model $subject): Model
    {
        if (! $subject->exists || $subject->getKey() === null) {
            throw new InvalidArgumentException('Affiliations require a persisted subject.');
        }

        return $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
    }

    private function lockAffiliation(Model $subject, Affiliation $affiliation): Affiliation
    {
        $locked = $affiliation->newQuery()->whereKey($affiliation->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->affiliatable_type !== $subject->getMorphClass() || (string) $locked->affiliatable_id !== (string) $subject->getKey()) {
            throw new AuthorizationException('Affiliation does not belong to this subject.');
        }

        return $locked;
    }

    private function persist(Affiliation $affiliation): void
    {
        if ($affiliation->is_primary) {
            $siblings = $affiliation->newQuery()
                ->where('affiliatable_type', $affiliation->affiliatable_type)
                ->where('affiliatable_id', $affiliation->affiliatable_id)
                ->where('is_primary', true);

            if ($affiliation->exists) {
                $siblings->whereKeyNot($affiliation->getKey());
            }

            $siblings->update(['is_primary' => false]);
        }

        if (! $affiliation->save()) {
            throw new RuntimeException('Affiliation persistence was cancelled.');
        }
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(['institution_id', 'affiliation_type', 'position', 'joined_at', 'left_at', 'is_primary']));
    }
}
