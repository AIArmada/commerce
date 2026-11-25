<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationQuestion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteRegistrationsScopeDependents
{
    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        $column = match ($scopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $scopeType)),
        };

        DB::transaction(function () use ($column, $scopeId): void {
            EventRegistration::query()
                ->where($column, $scopeId)
                ->chunkById(200, function (iterable $registrations): void {
                    foreach ($registrations as $registration) {
                        $registration->delete();
                    }
                });

            EventRegistrationQuestion::query()
                ->where($column, $scopeId)
                ->chunkById(200, function (iterable $questions): void {
                    foreach ($questions as $question) {
                        $question->delete();
                    }
                });
        });
    }
}
