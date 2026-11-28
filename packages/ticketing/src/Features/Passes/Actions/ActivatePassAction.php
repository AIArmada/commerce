<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\States\Activated;
use Carbon\CarbonImmutable;

final readonly class ActivatePassAction
{
    public function handle(Pass $pass): Pass
    {
        return $pass->getConnection()->transaction(function () use ($pass): Pass {
            $locked = OwnerUiScope::apply(Pass::query(), includeGlobal: false)
                ->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status instanceof Activated) {
                return $locked;
            }

            $locked->activated_at ??= CarbonImmutable::now();
            $locked->status->transitionTo(Activated::class);

            return $locked;
        });
    }
}
