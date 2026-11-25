<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Queries;

use AIArmada\Events\Contracts\EventClassificationResolver;
use AIArmada\Events\Features\Taxonomy\Models\EventClassification;

final class DefaultEventClassificationResolver implements EventClassificationResolver
{
    public function resolve(string $eventId): array
    {
        $resolved = [];

        EventClassification::query()
            ->where('event_id', $eventId)
            ->whereNull('event_occurrence_id')
            ->whereNull('event_session_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (EventClassification $classification) use (&$resolved): void {
                $taxonomyCode = $classification->taxonomy_code;
                $termCode = $classification->term_code;

                if (! is_string($taxonomyCode) || $taxonomyCode === '' || ! is_string($termCode) || $termCode === '') {
                    return;
                }

                /** @var list<string> $codes */
                $codes = $resolved[$taxonomyCode] ?? [];
                $codes[] = $termCode;
                $resolved[$taxonomyCode] = $codes;
            });

        return $resolved;
    }
}
