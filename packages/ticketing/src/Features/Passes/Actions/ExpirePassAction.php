<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Seating\Features\Reservations\Api\ReservationsApi;
use AIArmada\Ticketing\Events\PassExpired;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Queries\MapPassData;
use AIArmada\Ticketing\States\Expired;
use Carbon\CarbonImmutable;

final readonly class ExpirePassAction
{
    public function __construct(
        private ReservationsApi $reservations,
        private MapPassData $mapPassData,
    ) {}

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

            $this->reservations->releaseAllocations(
                allocToType: $locked->getMorphClass(),
                allocToId: (string) $locked->getKey(),
            );

            $payload = $this->mapPassData->handle($locked);
            $locked->getConnection()->afterCommit(fn () => event(new PassExpired($payload)));

            return $locked;
        });
    }
}
