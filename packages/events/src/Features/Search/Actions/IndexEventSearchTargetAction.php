<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\Actions;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Features\Search\EntryPoints\Jobs\BuildEventSearchDocumentJob;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;

final class IndexEventSearchTargetAction implements EventSearchIndexer
{
    public function index(mixed $target): void
    {
        if (! config('events.sync.build_search_documents')) {
            return;
        }

        $resolved = $this->resolveModelTarget($target);

        if ($resolved === null) {
            return;
        }

        [$targetType, $targetId] = $resolved;

        if (config('events.search.queue_indexing', false)) {
            $this->dispatchJob($targetType, $targetId);

            return;
        }

        app(UpsertEventSearchDocumentAction::class)->handle($targetType, $targetId);
    }

    public function remove(mixed $target): void
    {
        $resolved = $this->resolveModelTarget($target);

        if ($resolved === null) {
            return;
        }

        [$targetType, $targetId] = $resolved;

        app(DeleteEventSearchDocumentAction::class)->handle($targetType, $targetId);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function resolveModelTarget(mixed $target): ?array
    {
        if (! $target instanceof Model) {
            return null;
        }

        $eventClass = ModelResolver::eventClass();
        $occurrenceClass = ModelResolver::occurrenceClass();
        $sessionClass = ModelResolver::sessionClass();

        if (is_a($target, $eventClass)) {
            return [$eventClass, (string) $target->getKey()];
        }

        if (is_a($target, $occurrenceClass)) {
            return [$occurrenceClass, (string) $target->getKey()];
        }

        if (is_a($target, $sessionClass)) {
            return [$sessionClass, (string) $target->getKey()];
        }

        if (! is_a($target, ModelResolver::searchDocumentClass())) {
            return null;
        }

        $target->loadMissing('searchable');

        $searchable = $target->getRelationValue('searchable');

        return $searchable instanceof Model ? $this->resolveModelTarget($searchable) : null;
    }

    private function dispatchJob(string $targetType, string $targetId): void
    {
        $owner = $this->ownerContextAttributes($targetType, $targetId);

        if ($owner === null) {
            return;
        }

        $job = new BuildEventSearchDocumentJob(
            targetType: $targetType,
            targetId: $targetId,
            ownerType: $owner['type'],
            ownerId: $owner['id'],
            ownerIsGlobal: $owner['global'],
        );

        $connection = config('events.search.queue_connection');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        $queue = config('events.search.queue_name');

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        $job->afterCommit();

        dispatch($job);
    }

    /**
     * @return array{type: string|null, id: string|int|null, global: bool}|null
     */
    private function ownerContextAttributes(string $targetType, string $targetId): ?array
    {
        $target = $this->findModel($targetType, $targetId);

        if (! $target instanceof Model) {
            return null;
        }

        $event = $this->resolveEventFor($target, $targetType);

        if (! $event instanceof Model) {
            return null;
        }

        $event->loadMissing('owner');

        $owner = $event->getRelationValue('owner');

        if (! $owner instanceof Model) {
            return ['type' => null, 'id' => null, 'global' => true];
        }

        return [
            'type' => $owner->getMorphClass(),
            'id' => $owner->getKey(),
            'global' => false,
        ];
    }

    private function findModel(string $targetType, string $targetId): ?Model
    {
        $classes = [
            ModelResolver::eventClass(),
            ModelResolver::occurrenceClass(),
            ModelResolver::sessionClass(),
        ];

        foreach ($classes as $class) {
            if (is_a($targetType, $class, true)) {
                return $class::query()->find($targetId);
            }
        }

        return null;
    }

    private function resolveEventFor(Model $target, string $targetType): ?Model
    {
        if (is_a($targetType, ModelResolver::eventClass(), true)) {
            return $target;
        }

        $target->loadMissing('event');

        $event = $target->getRelationValue('event');

        return $event instanceof Model ? $event : null;
    }
}
