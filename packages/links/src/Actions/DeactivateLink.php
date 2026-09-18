<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkDeactivated;
use AIArmada\Links\Models\Link;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

final class DeactivateLink
{
    use AsAction;

    public function handle(Link $link): Link
    {
        if ($link->deactivated_at !== null) {
            return $link;
        }

        $link->update(['deactivated_at' => CarbonImmutable::now()]);

        event(new LinkDeactivated($link));

        return $link->refresh();
    }
}
