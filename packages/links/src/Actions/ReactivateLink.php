<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkReactivated;
use AIArmada\Links\Models\Link;
use Lorisleiva\Actions\Concerns\AsAction;

final class ReactivateLink
{
    use AsAction;

    public function handle(Link $link): Link
    {
        if ($link->deactivated_at === null) {
            return $link;
        }

        $link->update(['deactivated_at' => null]);

        event(new LinkReactivated($link));

        return $link->refresh();
    }
}
