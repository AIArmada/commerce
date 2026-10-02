<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventLanguage;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventScopeResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Replace the language rows owned by one event, occurrence, or session scope.
 *
 * Rows outside the given scope are never touched.
 */
final class SyncEventLanguagesAction
{
    /**
     * @param  Event|EventOccurrence|EventSession  $scope  Scope that owns the synced rows.
     * @param  list<string>  $languageCodes  Language codes in display order.
     * @param  array<string, mixed>|null  $metadata  Metadata stored on every synced row.
     */
    public function handle(
        Event | EventOccurrence | EventSession $scope,
        array $languageCodes,
        string $usageType = 'primary',
        ?array $metadata = null,
    ): int {
        $resolved = EventScopeResolver::resolve($scope);
        $eventId = $resolved['event_id'];
        $occurrenceId = $resolved['occurrence_id'];
        $sessionId = $resolved['session_id'];

        if ($usageType === '') {
            throw new InvalidArgumentException('Language usage type must be a non-empty string.');
        }

        $codes = [];

        foreach ($languageCodes as $code) {
            if (! is_string($code) || mb_trim($code) === '') {
                throw new InvalidArgumentException('Each language code must be a non-empty string.');
            }

            $codes[] = mb_trim($code);
        }

        $codes = array_values(array_unique($codes));

        return DB::transaction(function () use ($eventId, $occurrenceId, $sessionId, $codes, $usageType, $metadata): int {
            $query = EventLanguage::query()->where('event_id', $eventId);

            if ($occurrenceId === null) {
                $query->whereNull('event_occurrence_id');
            } else {
                $query->where('event_occurrence_id', $occurrenceId);
            }

            if ($sessionId === null) {
                $query->whereNull('event_session_id');
            } else {
                $query->where('event_session_id', $sessionId);
            }

            $query->delete();

            foreach ($codes as $index => $code) {
                EventLanguage::query()->create([
                    'event_id' => $eventId,
                    'event_occurrence_id' => $occurrenceId,
                    'event_session_id' => $sessionId,
                    'language_code' => $code,
                    'usage_type' => $usageType,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                    'metadata' => $metadata,
                ]);
            }

            return count($codes);
        });
    }
}
