<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkDeactivated;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Persistence\LockOwnedLink;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

final class DeactivateLink
{
    use AsAction;

    public function handle(Link $link): Link
    {
        [$resolved, $changed] = $link->getConnection()->transaction(function () use ($link): array {
            $resolved = app(LockOwnedLink::class)->handle($link);

            if ($resolved->deactivated_at !== null) {
                return [$resolved, false];
            }

            $resolved->update(['deactivated_at' => CarbonImmutable::now()]);

            return [$resolved, true];
        });

        $link->setRawAttributes($resolved->getAttributes(), true);

        if ($changed) {
            event(new LinkDeactivated($resolved));
        }

        return $resolved;
    }
}
