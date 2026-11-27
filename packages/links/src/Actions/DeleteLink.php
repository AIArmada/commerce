<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Models\Link;
use AIArmada\Links\Persistence\DeleteLockedLink;
use AIArmada\Links\Persistence\LockOwnedLink;

final readonly class DeleteLink
{
    public function __construct(private LockOwnedLink $lock, private DeleteLockedLink $delete) {}

    public function handle(Link $link): bool
    {
        return $link->getConnection()->transaction(
            fn (): bool => $this->delete->handle($this->lock->handle($link)),
        );
    }
}
