<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Actions;

use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Models\Variant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final readonly class DeleteSelectedVariants
{
    /** @param Collection<int|string, Variant> $records */
    public function handle(Collection $records): void
    {
        (new Variant)->getConnection()->transaction(function () use ($records): void {
            foreach ($records as $record) {
                if (! $record instanceof Variant || ! ExperimentAccess::canMutateVariant($record)) {
                    throw new RuntimeException('Global growth variants can only be deleted from explicit global context.');
                }
            }

            Gate::authorize('deleteAny', Variant::class);

            foreach ($records as $record) {
                Gate::authorize('delete', $record);
            }

            foreach ($records as $record) {
                $record->delete();
            }
        });
    }
}
