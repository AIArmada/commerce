<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\Actions;

use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;

final class DeleteEventSearchDocumentAction
{
    public function handle(string $targetType, string $targetId): void
    {
        $column = $this->scopeColumn($targetType);

        if ($column === null) {
            return;
        }

        $documentClass = ModelResolver::searchDocumentClass();

        $documentClass::query()
            ->withoutGlobalScope(EventWriteGuard::OWNER_SCOPE)
            ->where($column, $targetId)
            ->delete();
    }

    private function scopeColumn(string $targetType): ?string
    {
        if (is_a($targetType, ModelResolver::eventClass(), true)) {
            return 'event_id';
        }

        if (is_a($targetType, ModelResolver::occurrenceClass(), true)) {
            return 'event_occurrence_id';
        }

        if (is_a($targetType, ModelResolver::sessionClass(), true)) {
            return 'event_session_id';
        }

        return null;
    }
}
