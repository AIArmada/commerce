<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Actions;

use AIArmada\Events\Features\Changes\Models\EventUpdate;
use Illuminate\Support\Facades\DB;

final class DeleteUpdateDependents
{
    public function handle(EventUpdate $update): void
    {
        DB::transaction(function () use ($update): void {
            $update->items()->chunkById(200, function (iterable $items): void {
                foreach ($items as $item) {
                    $item->delete();
                }
            });
        });
    }
}
