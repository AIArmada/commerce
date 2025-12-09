<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Registry for stockable model types that can be filtered in resources.
 */
final class StockableTypeRegistry
{
    /**
     * Get all stockable type definitions from config.
     *
     * @return array<int, array{label: string, model: class-string, title_attribute: string, search_attributes?: array<string>}>
     */
    public function definitions(): array
    {
        return config('filament-stock.stockables', []);
    }

    /**
     * Check if any stockable definitions exist.
     */
    public function hasDefinitions(): bool
    {
        return count($this->definitions()) > 0;
    }

    /**
     * Get options for select fields.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->definitions() as $definition) {
            if (isset($definition['model'], $definition['label'])) {
                $options[$definition['model']] = $definition['label'];
            }
        }

        return $options;
    }

    /**
     * Get definition by model class.
     *
     * @return array{label: string, model: class-string, title_attribute: string, search_attributes?: array<string>}|null
     */
    public function getDefinition(string $modelClass): ?array
    {
        foreach ($this->definitions() as $definition) {
            if (($definition['model'] ?? null) === $modelClass) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Search for stockables of a given type.
     *
     * @return array<string, string>
     */
    public function search(string $modelClass, ?string $search): array
    {
        $definition = $this->getDefinition($modelClass);

        if ($definition === null) {
            return [];
        }

        /** @var class-string<Model> $model */
        $model = $definition['model'];
        $titleAttribute = $definition['title_attribute'];
        $searchAttributes = $definition['search_attributes'] ?? [$titleAttribute];

        $query = $model::query();

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($searchAttributes, $search): void {
                foreach ($searchAttributes as $attribute) {
                    $q->orWhere($attribute, 'like', "%{$search}%");
                }
            });
        }

        return $query
            ->limit(50)
            ->get()
            ->mapWithKeys(fn ($item) => [$item->getKey() => $item->{$titleAttribute}])
            ->toArray();
    }

    /**
     * Resolve label for a stockable key.
     */
    public function resolveLabelForKey(string $modelClass, mixed $key): ?string
    {
        $definition = $this->getDefinition($modelClass);

        if ($definition === null) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = $definition['model'];
        $titleAttribute = $definition['title_attribute'];

        $record = $model::find($key);

        return $record?->{$titleAttribute};
    }
}
