<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkReactivated;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Persistence\LockOwnedLink;
use Lorisleiva\Actions\Concerns\AsAction;

final class ReactivateLink
{
    use AsAction;

    public function handle(Link $link): Link
    {
        [$resolved, $changed] = $link->getConnection()->transaction(function () use ($link): array {
            $resolved = app(LockOwnedLink::class)->handle($link);

            if ($resolved->deactivated_at === null) {
                return [$resolved, false];
            }

            $resolved->update(['deactivated_at' => null]);

            return [$resolved, true];
        });

        $link->setRawAttributes($resolved->getAttributes(), true);

        if ($changed) {
            event(new LinkReactivated($resolved));
        }

        return $resolved;
    }
}
