<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Events\PassCancelled;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\States\Cancelled;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final readonly class CancelPassAction
{
    public function handle(Pass $pass, ?string $reason = null): Pass
    {
        return $pass->getConnection()->transaction(function () use ($pass, $reason): Pass {
            $locked = OwnerUiScope::apply(Pass::query(), includeGlobal: false)
                ->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status instanceof Cancelled) {
                return $locked;
            }

            $locked->cancelled_at ??= CarbonImmutable::now();
            $locked->status_reason = $reason;
            $locked->status->transitionTo(Cancelled::class);

            Event::dispatch(new PassCancelled($locked));

            return $locked;
        });
    }
}
