<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Models\Link;
use AIArmada\Links\Persistence\LockOwnedLink;

final readonly class DeleteLink
{
    public function __construct(private LockOwnedLink $lock) {}

    public function handle(Link $link): bool
    {
        return $link->getConnection()->transaction(
            fn (): bool => (bool) $this->lock->handle($link)->delete(),
        );
    }
}
