<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Queries;

use AIArmada\Events\Contracts\EventRegistrationQuestionResolver;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Data\EventRegistrationQuestionData;
use AIArmada\Events\Data\EventRegistrationQuestionStatus;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationQuestion;

final class DefaultEventRegistrationQuestionResolver implements EventRegistrationQuestionResolver
{
    public function __construct(
        private readonly EventRegistrationScopeResolver $scopeResolver,
    ) {}

    /**
     * @return list<EventRegistrationQuestionData>
     */
    public function resolve(string $targetType, string $targetId): array
    {
        $scope = $this->scopeResolver->resolve($targetType, $targetId);

        $levels = [
            [
                'event_id' => $scope->eventId,
                'event_occurrence_id' => null,
                'event_session_id' => null,
            ],
        ];

        if ($scope->occurrenceId !== null) {
            $levels[] = [
                'event_id' => $scope->eventId,
                'event_occurrence_id' => $scope->occurrenceId,
                'event_session_id' => null,
            ];
        }

        if ($scope->sessionId !== null) {
            $levels[] = [
                'event_id' => $scope->eventId,
                'event_occurrence_id' => $scope->occurrenceId,
                'event_session_id' => $scope->sessionId,
            ];
        }

        $effective = [];

        foreach ($levels as $level) {
            $questions = EventRegistrationQuestion::query()
                ->where('event_id', $level['event_id'])
                ->where('event_occurrence_id', $level['event_occurrence_id'])
                ->where('event_session_id', $level['event_session_id'])
                ->where('status', EventRegistrationQuestionStatus::Active->value)
                ->ordered()
                ->get();

            foreach ($questions as $question) {
                $effective[(string) $question->field_key] = $question;
            }
        }

        usort(
            $effective,
            static fn (EventRegistrationQuestion $left, EventRegistrationQuestion $right): int => ($left->order_column ?? PHP_INT_MAX) <=> ($right->order_column ?? PHP_INT_MAX),
        );

        return array_values(array_map(
            static fn (EventRegistrationQuestion $question): EventRegistrationQuestionData => new EventRegistrationQuestionData(
                id: (string) $question->getKey(),
                eventId: (string) $question->event_id,
                occurrenceId: $question->event_occurrence_id !== null ? (string) $question->event_occurrence_id : null,
                sessionId: $question->event_session_id !== null ? (string) $question->event_session_id : null,
                fieldKey: (string) $question->field_key,
                question: (string) $question->question,
                description: $question->description,
                type: $question->type,
                options: $question->options ?? [],
                isRequired: (bool) $question->is_required,
                status: $question->status,
                orderColumn: (int) ($question->order_column ?? PHP_INT_MAX),
                metadata: $question->metadata,
            ),
            $effective,
        ));
    }
}
