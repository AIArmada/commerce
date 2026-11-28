<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Events\PassExpired;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\States\Expired;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final readonly class ExpirePassAction
{
    public function handle(Pass $pass): Pass
    {
        return $pass->getConnection()->transaction(function () use ($pass): Pass {
            /** @var Pass $locked */
            $locked = OwnerUiScope::apply(Pass::query(), includeGlobal: false)
                ->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status instanceof Expired) {
                return $locked;
            }

            $locked->expired_at ??= CarbonImmutable::now();
            $locked->status->transitionTo(Expired::class);

            Event::dispatch(new PassExpired($locked));

            return $locked;
        });
    }
}
