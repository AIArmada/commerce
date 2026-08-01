<?php

declare(strict_types=1);

namespace AIArmada\Persons\Actions;

use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Models\Title;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ReorderTitleAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Title
    {
        return DB::transaction(function () use ($attributes): Title {
            $title = new Title;
            $title->fill($attributes);

            $titles = $this->scope($title)->lockForUpdate()->get();
            $ordered = $this->insertAt($titles, $title, (int) ($attributes['sort_order'] ?? 1));

            $this->moveToTemporaryRange($titles);
            $this->persistOrder($ordered);

            $title->sort_order = $this->positionOf($ordered, $title);
            $title->save();

            return $title;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Title $title, array $attributes): Title
    {
        return DB::transaction(function () use ($title, $attributes): Title {
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

            $this->moveToTemporaryRange($oldTitles);
            if ($oldScope !== $newScope) {
                $this->moveToTemporaryRange($newTitles);
            }

            $this->persistOrder($oldOrdered, exclude: $title);
            if ($oldScope !== $newScope) {
                $this->persistOrder($newOrdered, exclude: $title);
            } else {
                $this->persistOrder($newOrdered, exclude: $title);
            }

            $title->sort_order = $this->positionOf($newOrdered, $title);
            $title->save();

            return $title;
        });
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

    /** @param Collection<int, Title> $titles */
    private function moveToTemporaryRange(Collection $titles): void
    {
        if ($titles->isEmpty()) {
            return;
        }

        $offset = (int) $titles->max('sort_order') + $titles->count() + 1;
        $this->bulkSetOrder($titles->mapWithKeys(
            fn (Title $title): array => [(string) $title->getKey() => (int) $title->sort_order + $offset],
        )->all());
    }

    /**
     * @param  Collection<int, Title>  $titles
     */
    private function persistOrder(Collection $titles, ?Title $exclude = null): void
    {
        $orders = [];

        foreach ($titles as $index => $title) {
            if ($exclude?->is($title)) {
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
        $connection = DB::connection();

        foreach ($orders as $id => $order) {
            $quotedId = $connection->getPdo()->quote($id);
            $quotedCases[] = "WHEN {$quotedId} THEN " . (int) $order;
        }

        Title::query()
            ->whereIn('id', array_keys($orders))
            ->update([
                'sort_order' => DB::raw('CASE id ' . implode(' ', $quotedCases) . ' ELSE sort_order END'),
            ]);
    }

    /** @param Collection<int, Title> $titles */
    private function positionOf(Collection $titles, Title $title): int
    {
        return $titles->search(fn (Title $item): bool => $item->is($title)) + 1;
    }
}
