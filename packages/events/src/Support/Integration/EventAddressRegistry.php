<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Integration;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventAddressable;
use AIArmada\Events\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class EventAddressRegistry
{
    /**
     * @return array<class-string<Model>, string>
     */
    public static function options(): array
    {
        $configuredModels = config('events.addresses.models', [Venue::class]);
        $models = [];

        if (! is_array($configuredModels)) {
            return [Venue::class => class_basename(Venue::class)];
        }

        foreach ($configuredModels as $key => $value) {
            $modelClass = null;
            $label = null;

            if (is_string($value) && is_a($value, Model::class, true)) {
                $modelClass = $value;
            } elseif (is_string($key) && is_a($key, Model::class, true)) {
                $modelClass = $key;
                $label = is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
            }

            if ($modelClass === null) {
                continue;
            }

            if (! is_a($modelClass, EventAddressable::class, true)) {
                throw new RuntimeException(sprintf(
                    'The [%s] address model must implement %s.',
                    $modelClass,
                    EventAddressable::class,
                ));
            }

            $models[$modelClass] = $label ?? class_basename($modelClass);
        }

        if ($models === []) {
            $models[Venue::class] = class_basename(Venue::class);
        }

        return $models;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::options();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, string>
     */
    public static function searchResults(string $modelClass, string $search): array
    {
        $query = self::queryFor($modelClass);
        $search = mb_trim($search);
        $searchableColumns = self::searchableColumns($modelClass);

        if ($search !== '' && $searchableColumns !== []) {
            $query->where(function (Builder $builder) use ($search, $searchableColumns): void {
                foreach ($searchableColumns as $index => $column) {
                    if ($index === 0) {
                        $builder->where($column, 'like', '%' . $search . '%');
                    } else {
                        $builder->orWhere($column, 'like', '%' . $search . '%');
                    }
                }
            });
        }

        return $query
            ->limit(50)
            ->get()
            ->mapWithKeys(function (Model $record) use ($modelClass): array {
                return [(string) $record->getKey() => self::optionLabel($modelClass, $record)];
            })
            ->all();
    }

    public static function optionLabel(?string $modelClass, mixed $value): ?string
    {
        if (! is_string($modelClass) || ! is_a($modelClass, Model::class, true)) {
            return null;
        }

        $record = $value instanceof Model && is_a($value, $modelClass, true)
            ? $value
            : self::resolveRecord($modelClass, $value);

        if (! $record instanceof EventAddressable) {
            return null;
        }

        return app(EventAddressResolver::class)->label($record);
    }

    public static function resolveRecord(string $modelClass, mixed $value): ?Model
    {
        if (! is_scalar($value) || (string) $value === '') {
            return null;
        }

        if (! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('The [%s] address model is invalid.', $modelClass));
        }

        if (method_exists($modelClass, 'scopeForOwner') || method_exists($modelClass, 'scopeGlobalOnly')) {
            return OwnerWriteGuard::findOrFailForOwner(
                $modelClass,
                (string) $value,
                includeGlobal: true,
                message: sprintf('The selected %s is not accessible in the current owner scope.', class_basename($modelClass)),
            );
        }

        $record = $modelClass::query()->whereKey((string) $value)->first();

        return $record;
    }

    /**
     * @return Builder<Model>
     */
    private static function queryFor(string $modelClass): Builder
    {
        /** @var Builder<Model> $query */
        $query = $modelClass::query();

        if (method_exists($modelClass, 'scopeForOwner') || method_exists($modelClass, 'scopeGlobalOnly')) {
            return OwnerUiScope::apply($query, includeGlobal: true);
        }

        return $query;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private static function searchableColumns(string $modelClass): array
    {
        $table = (new $modelClass)->getTable();
        $candidateColumns = ['name', 'title', 'label', 'slug', 'line1', 'city', 'state', 'postcode', 'external_id'];

        return array_values(array_filter(
            $candidateColumns,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
    }
}
