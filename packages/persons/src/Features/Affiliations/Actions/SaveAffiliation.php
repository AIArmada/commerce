<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Affiliations\Actions;

use AIArmada\Persons\Features\Affiliations\Models\Affiliation;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class SaveAffiliation
{
    /** @param array<string, mixed> $attributes */
    public function create(Model $subject, array $attributes): Affiliation
    {
        if (! $subject->exists || $subject->getKey() === null) {
            throw new InvalidArgumentException('Affiliations require a persisted subject.');
        }

        $affiliation = new Affiliation;
        $affiliation->fill($this->attributes($attributes));
        $affiliation->affiliatable()->associate($subject);
        $affiliation->save();

        return $affiliation;
    }

    /** @param array<string, mixed> $attributes */
    public function update(Affiliation $affiliation, array $attributes): Affiliation
    {
        $affiliation->fill($this->attributes($attributes));
        $affiliation->save();

        return $affiliation;
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(array $attributes): array
    {
        unset($attributes['affiliatable_id'], $attributes['affiliatable_type']);

        return $attributes;
    }
}
