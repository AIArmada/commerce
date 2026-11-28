<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Actions;

use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Features\Titles\Models\Title;
use AIArmada\Persons\Features\Titles\Models\TitleCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

final class ReorderTitleAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Title
    {
        return (new Title)->getConnection()->transaction(function () use ($attributes): Title {
            $title = new Title;
            $title->fill($attributes);
            $this->lockCategories([(string) $title->category_id]);

            $titles = $this->scope($title)->lockForUpdate()->get();
            $ordered = $this->insertAt($titles, $title, (int) ($attributes['sort_order'] ?? 1));

            $this->moveToTemporaryRange($titles);
            $this->persistOrder($ordered, exclude: $title);

            $title->sort_order = $this->positionOf($ordered, $title);
            if (! $title->save()) {
                throw new RuntimeException('Title persistence was cancelled.');
            }

            return $title;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Title $title, array $attributes): Title
    {
        return $title->getConnection()->transaction(function () use ($title, $attributes): Title {
            $originalCategory = (string) $title->getRawOriginal('category_id');
            $requestedCategory = array_key_exists('category_id', $attributes)
                ? (string) $attributes['category_id']
                : $originalCategory;

            $this->lockCategories([$originalCategory, $requestedCategory]);

            $current = $title->newQuery()->whereKey($title->getKey())->lockForUpdate()->firstOrFail();

            if ((string) $current->category_id !== $originalCategory) {
                throw ValidationException::withMessages(['category_id' => 'The title category has changed. Reload the title before editing it.']);
            }

            $title->setRawAttributes($current->getAttributes(), true);
            $oldScope = $this->scopeValues($title->getRawOriginal('category_id'), $title->getRawOriginal('usage_position'));

            $title->fill($attributes);

            $newScope = $this->scopeValues(
                (string) $title->category_id,
                $title->usage_position instanceof TitleUsagePosition
                    ? $title->usage_position->value
                    : (string) $title->usage_position,
            );

            $oldTitles = $this->scopeQuery($oldScope['category_id'], $oldScope['usage_position'])
                ->lockForUpdate()
                ->get();
            $newTitles = $oldScope === $newScope
                ? $oldTitles
                : $this->scopeQuery($newScope['category_id'], $newScope['usage_position'])
                    ->lockForUpdate()
                    ->get();

            $oldOrdered = $oldTitles->reject(fn (Title $item): bool => $item->is($title))->values();
            $newOrdered = $oldScope === $newScope
                ? $this->insertAt($oldOrdered, $title, (int) ($attributes['sort_order'] ?? $title->sort_order))
                : $this->insertAt($newTitles, $title, (int) ($attributes['sort_order'] ?? $title->sort_order));

            $temporaryOrders = $this->moveToTemporaryRange($oldTitles);
            $title->sort_order = $temporaryOrders[(string) $title->getKey()];
            $title->syncOriginalAttribute('sort_order');
            if ($oldScope !== $newScope) {
                $this->moveToTemporaryRange($newTitles);
            }

            if ($oldScope !== $newScope) {
                $this->persistOrder($oldOrdered, exclude: $title);
            }

            $this->persistOrder($newOrdered, exclude: $title);

            $title->sort_order = $this->positionOf($newOrdered, $title);
            if (! $title->save()) {
                throw new RuntimeException('Title persistence was cancelled.');
            }

            return $title;
        });
    }

    /** @param list<string> $categoryIds */
    private function lockCategories(array $categoryIds): void
    {
        $categoryIds = array_values(array_unique($categoryIds));
        $categories = TitleCategory::query()
            ->whereKey($categoryIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        if ($categories->count() !== count($categoryIds)) {
            throw ValidationException::withMessages(['category_id' => 'The selected title category does not exist.']);
        }
    }

    /** @return array{category_id: string, usage_position: string} */
    private function scopeValues(?string $categoryId, ?string $usagePosition): array
    {
        return [
            'category_id' => (string) $categoryId,
            'usage_position' => (string) $usagePosition,
        ];
    }

    private function scope(Title $title): mixed
    {
        return $this->scopeQuery(
            (string) $title->category_id,
            $title->usage_position instanceof TitleUsagePosition
                ? $title->usage_position->value
                : (string) $title->usage_position,
        );
    }

    private function scopeQuery(string $categoryId, string $usagePosition): mixed
    {
        return Title::query()
            ->where('category_id', $categoryId)
            ->where('usage_position', $usagePosition)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** @return Collection<int, Title> */
    private function insertAt(Collection $titles, Title $title, int $requestedPosition): Collection
    {
        $position = max(1, min($requestedPosition, $titles->count() + 1));
        $items = new Collection;
        $inserted = false;

        foreach ($titles as $item) {
            if ($item->is($title)) {
                continue;
            }

            if (! $inserted && $items->count() === $position - 1) {
                $items->push($title);
                $inserted = true;
            }

            $items->push($item);
        }

        if (! $inserted) {
            $items->push($title);
        }

        return $items->values();
    }

    /**
     * @param  Collection<int, Title>  $titles
     * @return array<string, int>
     */
    private function moveToTemporaryRange(Collection $titles): array
    {
        if ($titles->isEmpty()) {
            return [];
        }

        $offset = (int) $titles->max('sort_order') + $titles->count() + 1;
        $orders = $titles->mapWithKeys(
            fn (Title $title): array => [(string) $title->getKey() => (int) $title->sort_order + $offset],
        )->all();
        $this->bulkSetOrder($orders);

        return $orders;
    }

    /**
     * @param  Collection<int, Title>  $titles
     */
    private function persistOrder(Collection $titles, ?Title $exclude = null): void
    {
        $orders = [];

        foreach ($titles as $index => $title) {
            if ($title === $exclude || $exclude?->is($title)) {
                continue;
            }

            $orders[(string) $title->getKey()] = $index + 1;
        }

        $this->bulkSetOrder($orders);
    }

    /**
     * Update an entire ordering scope in one statement. Ordering already used
     * direct query updates (and therefore did not dispatch model events); the
     * CASE expression preserves that behavior while removing the per-title N+1.
     *
     * @param  array<string, int>  $orders
     */
    private function bulkSetOrder(array $orders): void
    {
        if ($orders === []) {
            return;
        }

        $quotedCases = [];
        $connection = (new Title)->getConnection();

        foreach ($orders as $id => $order) {
            $quotedId = $connection->getPdo()->quote($id);
            $quotedCases[] = "WHEN {$quotedId} THEN " . (int) $order;
        }

        Title::query()
            ->whereIn('id', array_keys($orders))
            ->update([
                'sort_order' => $connection->raw('CASE id ' . implode(' ', $quotedCases) . ' ELSE sort_order END'),
            ]);
    }

    /** @param Collection<int, Title> $titles */
    private function positionOf(Collection $titles, Title $title): int
    {
        $position = $titles->search(fn (Title $item): bool => $item->is($title));

        if ($position === false) {
            throw new LogicException('The title was not found in its ordering scope.');
        }

        return $position + 1;
    }
}
