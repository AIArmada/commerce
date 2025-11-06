<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class OwnerTypeRegistry
{
    /** @var Collection<int, array<string, mixed>> */
    private Collection $definitions;

    public function __construct()
    {
        /** @var array<int, mixed> $configData */
        $configData = config('filament-vouchers.owners', []);
        /** @var Collection<int, mixed> $definitions */
        $definitions = collect($configData)
            ->filter(static function (mixed $definition): bool {
                if (! is_array($definition)) {
                    return false;
                }

                $model = Arr::get($definition, 'model');

                if (! is_string($model) || $model === '') {
                    return false;
                }

                return class_exists($model) && is_subclass_of($model, Model::class);
            })
            ->values();

        $this->definitions = $definitions;
    }

    public function hasDefinitions(): bool
    {
        return $this->definitions->isNotEmpty();
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->definitions
            ->mapWithKeys(static function (array $definition): array {
                /** @var class-string<Model> $model */
                $model = $definition['model'];
                $label = (string) Arr::get($definition, 'label', class_basename($model));

                return [$model => $label];
            })
            ->toArray();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, mixed>|null
     */
    public function definitionFor(string $modelClass): ?array
    {
        return $this->definitions
            ->first(static fn (array $definition): bool => $definition['model'] === $modelClass);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int|string, string>
     */
    public function search(string $modelClass, ?string $search = null, int $limit = 20): array
    {
        $definition = $this->definitionFor($modelClass);

        if (! $definition) {
            return [];
        }

        /** @var class-string<Model> $modelClass */
        $query = $modelClass::query()->limit($limit);

        $search = $search ? mb_trim($search) : null;
        $searchableColumns = Arr::wrap($definition['search_attributes'] ?? []);

        if ($search !== null && $search !== '') {
            if ($searchableColumns === []) {
                $query->where(
                    Arr::get($definition, 'title_attribute', $modelClass::query()->getModel()->getKeyName()),
                    'like',
                    "%{$search}%"
                );
            } else {
                $query->where(function ($builder) use ($search, $searchableColumns): void {
                    foreach ($searchableColumns as $column) {
                        $builder->orWhere((string) $column, 'like', "%{$search}%");
                    }
                });
            }
        }

        return $query
            ->orderBy(Arr::get($definition, 'title_attribute', $modelClass::query()->getModel()->getKeyName()))
            ->get()
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => $this->formatLabel($record, $definition)])
            ->toArray();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function resolveLabelForKey(string $modelClass, mixed $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        $definition = $this->definitionFor($modelClass);

        if (! $definition) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $record = $modelClass::query()->find($key);

        if (! $record instanceof Model) {
            return null;
        }

        return $this->formatLabel($record, $definition);
    }

    public function resolveDisplayLabel(Model $owner): string
    {
        $definition = $this->definitionFor($owner::class);

        if ($definition) {
            return $this->formatLabel($owner, $definition);
        }

        if (method_exists($owner, 'getDisplayNameAttribute')) {
            /** @phpstan-ignore-next-line */
            return (string) $owner->getAttribute('display_name');
        }

        return (string) $owner;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function formatLabel(Model $model, array $definition): string
    {
        $titleAttribute = (string) Arr::get($definition, 'title_attribute', $model->getKeyName());
        $subtitleAttribute = Arr::get($definition, 'subtitle_attribute');

        $title = Str::of((string) data_get($model, $titleAttribute))->trim();
        $title = $title->isEmpty() ? (string) $model->getKey() : $title;

        if (! $subtitleAttribute) {
            return (string) $title;
        }

        $subtitle = Str::of((string) data_get($model, $subtitleAttribute))->trim();

        if ($subtitle->isEmpty()) {
            return (string) $title;
        }

        return sprintf('%s — %s', $title, $subtitle);
    }
}
