<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Processes bulk-action selections in bounded ID chunks.
 *
 * Filament hydrates the full selection before the bulk callback runs. This
 * processor immediately reduces the selection to IDs (freeing the hydrated
 * models) and re-reads each chunk through an owner-scoped query, so memory
 * stays flat no matter how many rows were selected.
 *
 * Missing or inaccessible rows fail closed: a chunk that does not resolve
 * every requested ID throws instead of silently skipping rows.
 */
final class BulkRecordProcessor
{
    /**
     * @template TModel of Model
     *
     * @param  iterable<TModel>  $records
     * @param  class-string<TModel>  $modelClass
     * @param  Closure(TModel): void  $each
     */
    public static function eachInChunks(iterable $records, string $modelClass, Closure $each, int $chunkSize = 100): void
    {
        $ids = collect($records)
            ->filter(static fn (mixed $record): bool => $record instanceof Model && $record->getKey() !== null)
            ->map(static fn (Model $record): string => (string) $record->getKey())
            ->unique()
            ->values()
            ->all();

        unset($records);

        if ($ids === []) {
            return;
        }

        foreach (array_chunk($ids, max(1, $chunkSize)) as $idChunk) {
            /** @var Collection<int, TModel> $chunk */
            $chunk = OwnerUiScope::apply($modelClass::query()->whereKey($idChunk), includeGlobal: false)->get();

            $resolved = $chunk
                ->map(static fn (Model $record): string => (string) $record->getKey())
                ->all();

            if (array_diff($idChunk, $resolved) !== []) {
                throw new AuthorizationException('One or more selected records are not accessible in the current owner scope.');
            }

            foreach ($chunk as $record) {
                $each($record);
            }

            unset($chunk);
        }
    }
}
