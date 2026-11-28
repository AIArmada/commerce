<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Events\PassRevoked;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\States\Revoked;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final readonly class RevokePassAction
{
    public function handle(Pass $pass, ?string $reason = null): Pass
    {
        return $pass->getConnection()->transaction(function () use ($pass, $reason): Pass {
            $locked = OwnerUiScope::apply(Pass::query(), includeGlobal: false)
                ->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status instanceof Revoked) {
                return $locked;
            }

            $locked->revoked_at ??= CarbonImmutable::now();
            $locked->status_reason = $reason;
            $locked->status->transitionTo(Revoked::class);

            Event::dispatch(new PassRevoked($locked));

            return $locked;
        });
    }
}
