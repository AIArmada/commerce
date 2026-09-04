<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventRegistrationQuestionResolver;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Enums\EventRegistrationQuestionStatus;
use AIArmada\Events\Models\EventRegistrationQuestion;
use AIArmada\Events\Support\ModelResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class DefaultEventRegistrationQuestionResolver implements EventRegistrationQuestionResolver
{
    public function __construct(
        private readonly EventRegistrationScopeResolver $scopeResolver,
    ) {}

    /**
     * @return Collection<int, EventRegistrationQuestion>
     */
    public function resolve(Model $target): Collection
    {
        $scope = $this->scopeResolver->resolve($target);
        $questionClass = ModelResolver::registrationQuestionClass();

        $levels = [
            [
                'event_id' => $scope->event->getKey(),
                'event_occurrence_id' => null,
                'event_session_id' => null,
            ],
        ];

        if ($scope->occurrence !== null) {
            $levels[] = [
                'event_id' => $scope->event->getKey(),
                'event_occurrence_id' => $scope->occurrence->getKey(),
                'event_session_id' => null,
            ];
        }

        if ($scope->session !== null) {
            $levels[] = [
                'event_id' => $scope->event->getKey(),
                'event_occurrence_id' => $scope->occurrence?->getKey(),
                'event_session_id' => $scope->session->getKey(),
            ];
        }

        /** @var Collection<int, EventRegistrationQuestion> $effective */
        $effective = new Collection;

        foreach ($levels as $level) {
            $questions = $questionClass::query()
                ->where('event_id', $level['event_id'])
                ->where('event_occurrence_id', $level['event_occurrence_id'])
                ->where('event_session_id', $level['event_session_id'])
                ->where('status', EventRegistrationQuestionStatus::Active->value)
                ->ordered()
                ->get();

            foreach ($questions as $question) {
                $effective->put((string) $question->field_key, $question);
            }
        }

        return $effective
            ->sortBy(fn (EventRegistrationQuestion $question): int => (int) ($question->order_column ?? PHP_INT_MAX))
            ->values();
    }
}
