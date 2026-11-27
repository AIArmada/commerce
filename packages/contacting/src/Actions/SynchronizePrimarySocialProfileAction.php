<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Models\SocialProfile;
use Closure;
use Illuminate\Database\Eloquent\Builder;

final readonly class SynchronizePrimarySocialProfileAction
{
    /** @param Closure(): bool $persist */
    public function execute(SocialProfile $record, Closure $persist): bool
    {
        return $record->getConnection()->transaction(function () use ($record, $persist): bool {
            $this->demoteSiblings($record);

            return $persist();
        });
    }

    private function demoteSiblings(SocialProfile $record): void
    {
        if (! $record->is_primary) {
            return;
        }

        if ($record->socialable_type === null || $record->socialable_id === null) {
            return;
        }

        $record->socialable()->lockForUpdate()->firstOrFail();

        // The surrounding save transaction rolls this demotion back if a
        // later model guard or the database constraint rejects the write.
        SocialProfile::query()
            ->where('socialable_type', $record->socialable_type)
            ->where('socialable_id', $record->socialable_id)
            ->where('platform', $record->platform)
            ->where('purpose', $record->purpose)
            ->where('is_primary', true)
            ->when($record->exists, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
            ->where(function (Builder $query): void {
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
