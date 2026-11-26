<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\Geography\Actions;

use AIArmada\Addressing\Contracts\GeographyReferenceEditor;
use AIArmada\Addressing\Support\ModelResolver;
use Illuminate\Validation\ValidationException;

final readonly class EditGeographyReferenceAction implements GeographyReferenceEditor
{
    public function country(string $id, array $attributes): void
    {
        $class = ModelResolver::countryClass();
        $class::query()->getConnection()->transaction(function () use ($class, $id, $attributes): void {
            $record = $class::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $record->fill(array_intersect_key($attributes, array_flip(['name', 'native', 'phone_code', 'region', 'subregion'])));

            if (! is_string($record->name) || mb_trim($record->name) === '') {
                throw ValidationException::withMessages(['name' => 'A country name is required.']);
            }

            $record->saveOrFail();
        });
    }

    public function state(string $id, array $attributes): void
    {
        $class = ModelResolver::stateClass();
        $class::query()->getConnection()->transaction(function () use ($class, $id, $attributes): void {
            $record = $class::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $data = $this->coordinates($attributes);

            if ($record->code !== null && array_key_exists('name', $attributes)) {
                if (! is_string($attributes['name']) || mb_trim($attributes['name']) === '') {
                    throw ValidationException::withMessages(['name' => 'A state name is required.']);
                }

                $data['name'] = mb_trim($attributes['name']);
            }

            $record->fill($data)->saveOrFail();
        });
    }

    public function city(string $id, array $attributes): void
    {
        $class = ModelResolver::cityClass();
        $class::query()->getConnection()->transaction(function () use ($class, $id, $attributes): void {
            $record = $class::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $record->fill($this->coordinates($attributes))->saveOrFail();
        });
    }

    /** @return array<string, float|null> */
    private function coordinates(array $attributes): array
    {
        $data = [];

        foreach (['latitude' => 90, 'longitude' => 180] as $key => $limit) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $value = $attributes[$key];

            if ($value === null || $value === '') {
                $data[$key] = null;

                continue;
            }

            if (! is_numeric($value) || ! is_finite((float) $value) || abs((float) $value) > $limit) {
                throw ValidationException::withMessages([$key => 'The coordinate is outside its valid range.']);
            }

            $data[$key] = (float) $value;
        }

        return $data;
    }
}
