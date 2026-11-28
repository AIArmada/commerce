<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Models\Pass;

final readonly class DeletePass
{
    public function handle(Pass $record): bool
    {
        return $record->getConnection()->transaction(function () use ($record): bool {
            $pass = OwnerUiScope::apply(Pass::query(), includeGlobal: false)
                ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            return (bool) $pass->delete();
        });
    }
}
