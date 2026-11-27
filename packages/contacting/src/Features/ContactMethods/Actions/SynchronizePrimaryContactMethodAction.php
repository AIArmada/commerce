<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Actions;

use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use AIArmada\Contacting\Support\PrimarySaveCancelled;
use Illuminate\Database\Eloquent\Builder;

final readonly class SynchronizePrimaryContactMethodAction
{
    public function execute(ContactMethod $record): bool
    {
        try {
            return $record->getConnection()->transaction(function () use ($record): bool {
                if ($record->is_primary && (! $record->exists || $record->isDirty(['is_primary', 'contactable_type', 'contactable_id', 'type', 'purpose', 'owner_type', 'owner_id']))) {
                    $record->prepareForPrimaryWrite();
                    $this->demoteSiblings($record);
                }

                if (! $record->save()) {
                    throw new PrimarySaveCancelled;
                }

                return true;
            });
        } catch (PrimarySaveCancelled) {
            return false;
        }
    }

    private function demoteSiblings(ContactMethod $record): void
    {
        if (! $record->is_primary) {
            return;
        }

        if ($record->contactable_type === null || $record->contactable_id === null) {
            return;
        }

        $record->contactable()->lockForUpdate()->firstOrFail();

        // The surrounding save transaction rolls this demotion back if a
        // later model guard or the database constraint rejects the write.
        ContactMethod::query()
            ->where('contactable_type', $record->contactable_type)
            ->where('contactable_id', $record->contactable_id)
            ->where('type', $record->type)
            ->where('purpose', $record->purpose)
            ->where('is_primary', true)
            ->when($record->exists, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
            ->where(function (Builder $query) use ($record): void {
                if ($record->owner_type === null) {
                    $query->whereNull('owner_type')->whereNull('owner_id');

                    return;
                }

                $query->where('owner_type', $record->owner_type)
                    ->where('owner_id', $record->owner_id);
            })
            ->update(['is_primary' => false]);
    }
}
