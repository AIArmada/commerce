<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\Actions;

use AIArmada\Events\Contracts\EventSearchDocumentBuilder as EventSearchDocumentBuilderContract;
use AIArmada\Events\Contracts\EventSearchRelationProvider;
use AIArmada\Events\Features\Search\Queries\EventSearchDocumentBuilder;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;

final class UpsertEventSearchDocumentAction
{
    public function __construct(
        private readonly EventSearchDocumentBuilderContract $builder,
        private readonly EventSearchRelationProvider $relations,
    ) {}

    public function handle(string $targetType, string $targetId): void
    {
        $target = $this->resolveTarget($targetType, $targetId);

        if (! $target instanceof Model) {
            return;
        }

        $this->handleTarget($target, $targetType);
    }

    public function handleTarget(Model $target, string $targetType): void
    {
        $documentType = $this->documentType($targetType);

        if ($documentType === null) {
            return;
        }

        if ($this->isHidden($target, $targetType)) {
            app(DeleteEventSearchDocumentAction::class)->handle($targetType, (string) $target->getKey());

            return;
        }

        $lookup = $this->lookupAttributes($targetType, (string) $target->getKey());

        if ($lookup === []) {
            return;
        }

        $payload = $this->payloadFor($target, $targetType, (string) $target->getKey());

        if ($payload === []) {
            return;
        }

        $documentClass = ModelResolver::searchDocumentClass();

        $documentClass::query()->updateOrCreate($lookup, $payload);
    }

    private function resolveTarget(string $targetType, string $targetId): ?Model
    {
        $class = $this->targetClass($targetType);

        if ($class === null) {
            return null;
        }

        return $class::query()
            ->with($this->relations->relations())
            ->find($targetId);
    }

    /**
     * @return array<string, string>
     */
    private function lookupAttributes(string $targetType, string $targetId): array
    {
        if (is_a($targetType, ModelResolver::eventClass(), true)) {
            return ['event_id' => $targetId, 'document_type' => 'event'];
        }

        if (is_a($targetType, ModelResolver::occurrenceClass(), true)) {
            return ['event_occurrence_id' => $targetId, 'document_type' => 'occurrence'];
        }

        if (is_a($targetType, ModelResolver::sessionClass(), true)) {
            return ['event_session_id' => $targetId, 'document_type' => 'session'];
        }

        return [];
    }

    private function payloadFor(Model $target, string $targetType, string $targetId): array
    {
        if ($this->builder instanceof EventSearchDocumentBuilder) {
            return $this->builder->buildForTarget($target, (string) $this->documentType($targetType));
        }

        if (is_a($targetType, ModelResolver::eventClass(), true)) {
            return $this->builder->buildForEvent($targetId);
        }

        if (is_a($targetType, ModelResolver::occurrenceClass(), true)) {
            return $this->builder->buildForOccurrence($targetId);
        }

        if (is_a($targetType, ModelResolver::sessionClass(), true)) {
            return $this->builder->buildForSession($targetId);
        }

        return [];
    }

    private function isHidden(Model $target, string $targetType): bool
    {
        if ($target->getAttribute('visibility') === 'hidden') {
            return true;
        }

        if (is_a($targetType, ModelResolver::eventClass(), true)) {
            return false;
        }

        $target->loadMissing('event');

        $event = $target->getRelationValue('event');

        if ($event instanceof Model && $event->getAttribute('visibility') === 'hidden') {
            return true;
        }

        if (! is_a($targetType, ModelResolver::sessionClass(), true)) {
            return false;
        }

        $target->loadMissing('occurrence');

        $occurrence = $target->getRelationValue('occurrence');

        return $occurrence instanceof Model && $occurrence->getAttribute('visibility') === 'hidden';
    }

    /**
     * @return class-string<Model>|null
     */
    private function targetClass(string $targetType): ?string
    {
        foreach ([
            ModelResolver::eventClass(),
            ModelResolver::occurrenceClass(),
            ModelResolver::sessionClass(),
        ] as $class) {
            if (is_a($targetType, $class, true)) {
                return $class;
            }
        }

        return null;
    }

    private function documentType(string $targetType): ?string
    {
        if (is_a($targetType, ModelResolver::eventClass(), true)) {
            return 'event';
        }

        if (is_a($targetType, ModelResolver::occurrenceClass(), true)) {
            return 'occurrence';
        }

        if (is_a($targetType, ModelResolver::sessionClass(), true)) {
            return 'session';
        }

        return null;
    }
}
