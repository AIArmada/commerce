<?php

declare(strict_types=1);

namespace AIArmada\Links\Persistence;

use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;
use LogicException;

final class DeleteLockedLink
{
    public function handle(Link $link): bool
    {
        if ($link->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Link deletion requires a locked row inside a transaction.');
        }

        $click = new LinkClick;
        $click->setConnection($link->getConnectionName());
        $click->newQuery()->withoutOwnerScope()->where('link_id', $link->getKey())->delete();

        if (! $link->delete()) {
            throw new LogicException('The tracked link could not be deleted.');
        }

        return true;
    }
}
