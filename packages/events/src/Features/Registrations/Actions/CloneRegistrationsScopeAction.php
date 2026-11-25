<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationQuestion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CloneRegistrationsScopeAction
{
    public function __construct(
        private readonly CatalogApi $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function cloneForScope(
        string $sourceScopeType,
        string $sourceScopeId,
        string $targetScopeType,
        string $targetScopeId,
        array $options = [],
    ): void {
        if ($sourceScopeType !== $targetScopeType) {
            throw new InvalidArgumentException('Registration questions can only be cloned between the same scope type.');
        }

        $sourceColumn = match ($sourceScopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $sourceScopeType)),
        };

        $target = $this->catalog->describeScope($targetScopeType, $targetScopeId);

        if ($target === null) {
            throw new InvalidArgumentException(sprintf('The clone target scope [%s] does not exist.', $targetScopeId));
        }

        DB::transaction(function () use ($sourceColumn, $sourceScopeId, $target): void {
            $existingFieldKeys = EventRegistrationQuestion::query()
                ->where('event_id', $target->eventId)
                ->where('event_occurrence_id', $target->occurrenceId)
                ->where('event_session_id', $target->sessionId)
                ->pluck('field_key')
                ->all();

            EventRegistrationQuestion::query()
                ->where($sourceColumn, $sourceScopeId)
                ->chunkById(200, function (iterable $questions) use ($existingFieldKeys, $target): void {
                    foreach ($questions as $question) {
                        $fieldKey = (string) $question->field_key;

                        if (in_array($fieldKey, $existingFieldKeys, true)) {
                            continue;
                        }

                        $replica = $question->replicate(['id', 'created_at', 'updated_at']);
                        $replica->setAttribute('event_id', $target->eventId);
                        $replica->setAttribute('event_occurrence_id', $target->occurrenceId);
                        $replica->setAttribute('event_session_id', $target->sessionId);
                        $replica->save();

                        $existingFieldKeys[] = $fieldKey;
                    }
                });
        });
    }
}
