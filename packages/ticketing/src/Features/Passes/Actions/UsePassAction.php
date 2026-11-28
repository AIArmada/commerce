<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\States\Activated;
use AIArmada\Ticketing\States\Used;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class UsePassAction
{
    public function handle(Pass $pass): Pass
    {
        return $pass->getConnection()->transaction(function () use ($pass): Pass {
            $locked = OwnerUiScope::apply(Pass::query(), includeGlobal: false)
                ->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status instanceof Used) {
                return $locked;
            }

            if ($locked->status instanceof Activated && ! $locked->isValid()) {
                throw new AuthorizationException('A pass with an invalid registration cannot be used.');
            }

            $locked->used_at ??= CarbonImmutable::now();
            $locked->status->transitionTo(Used::class);

            return $locked;
        });
    }
}
