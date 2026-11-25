<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\Actions;

use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteSearchScopeDependents
{
    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        if ($scopeType === 'registration') {
            return;
        }

        $column = match ($scopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $scopeType)),
        };

        DB::transaction(function () use ($column, $scopeId): void {
            $documentClass = ModelResolver::searchDocumentClass();

            $documentClass::query()
                ->withoutGlobalScope(EventWriteGuard::OWNER_SCOPE)
                ->where($column, $scopeId)
                ->delete();
        });
    }
}
