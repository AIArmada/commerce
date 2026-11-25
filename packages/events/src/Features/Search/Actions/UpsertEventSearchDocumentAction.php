<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\Actions;

use AIArmada\Events\Contracts\EventSearchDocumentBuilder as EventSearchDocumentBuilderContract;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;

final class UpsertEventSearchDocumentAction
{
    public function __construct(
        private readonly EventSearchDocumentBuilderContract $builder,
    ) {}

    public function handle(string $targetType, string $targetId): void
    {
        $target = $this->resolveTarget($targetType, $targetId);

        if (! $target instanceof Model) {
            return;
        }

        if ($this->isHidden($target, $targetType)) {
            app(DeleteEventSearchDocumentAction::class)->handle($targetType, $targetId);

            return;
        }

        $lookup = $this->lookupAttributes($targetType, $targetId);

        if ($lookup === []) {
            return;
        }

        $payload = $this->payloadFor($targetType, $targetId);

        if ($payload === []) {
            return;
        }

        $documentClass = ModelResolver::searchDocumentClass();

        $documentClass::query()->updateOrCreate($lookup, $payload);
    }

    private function resolveTarget(string $targetType, string $targetId): ?Model
    {
        foreach ($this->supportedClasses() as $class) {
            if (is_a($targetType, $class, true)) {
                return $class::query()->find($targetId);
            }
        }

        return null;
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

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $targetType, string $targetId): array
    {
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

        return $event instanceof Model && $event->getAttribute('visibility') === 'hidden';
    }

    /**
     * @return list<class-string<Model>>
     */
    private function supportedClasses(): array
    {
        return [
            ModelResolver::eventClass(),
            ModelResolver::occurrenceClass(),
            ModelResolver::sessionClass(),
        ];
    }
}
