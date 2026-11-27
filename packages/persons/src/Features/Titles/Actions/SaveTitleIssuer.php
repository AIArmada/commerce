<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Actions;

use AIArmada\Persons\Features\Titles\Models\TitleIssuer;
use InvalidArgumentException;
use RuntimeException;

final readonly class SaveTitleIssuer
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TitleIssuer
    {
        $record = new TitleIssuer;

        return $record->getConnection()->transaction(function () use ($record, $attributes): TitleIssuer {
            $record->fill($this->attributes($attributes));
            if (! $record->save()) {
                throw new RuntimeException('Title issuer persistence was cancelled.');
            }

            return $record;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(TitleIssuer $record, array $attributes): TitleIssuer
    {
        return $record->getConnection()->transaction(function () use ($record, $attributes): TitleIssuer {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill($this->attributes($attributes));
            if (! $locked->save()) {
                throw new RuntimeException('Title issuer persistence was cancelled.');
            }

            return $locked;
        });
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(array $attributes): array
    {
        $data = array_intersect_key($attributes, array_flip(['issuer_name', 'issuer_type', 'country_id', 'institution_id']));

        if (array_key_exists('issuer_name', $data) && (! is_string($data['issuer_name']) || mb_trim($data['issuer_name']) === '')) {
            throw new InvalidArgumentException('A non-empty issuer name is required.');
        }

        return $data;
    }
}
