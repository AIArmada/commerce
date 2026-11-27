<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Credentials\Actions;

use AIArmada\Persons\Features\Credentials\Models\CredentialDefinition;
use InvalidArgumentException;

final readonly class SaveCredentialDefinition
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CredentialDefinition
    {
        $record = new CredentialDefinition;

        return $record->getConnection()->transaction(function () use ($record, $attributes): CredentialDefinition {
            $record->fill($this->attributes($attributes));
            $record->save();

            return $record;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(CredentialDefinition $record, array $attributes): CredentialDefinition
    {
        return $record->getConnection()->transaction(function () use ($record, $attributes): CredentialDefinition {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill($this->attributes($attributes));
            $locked->save();

            return $locked;
        });
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(array $attributes): array
    {
        $data = array_intersect_key($attributes, array_flip(['name', 'short_form', 'field', 'credential_type', 'language_code']));

        if (array_key_exists('name', $data) && (! is_string($data['name']) || mb_trim($data['name']) === '')) {
            throw new InvalidArgumentException('A non-empty credential name is required.');
        }

        return $data;
    }
}
