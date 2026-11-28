<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Seating\Features\Reservations\Api\ReservationsApi;
use AIArmada\Ticketing\Events\PassCancelled;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Queries\MapPassData;
use AIArmada\Ticketing\States\Cancelled;
use Carbon\CarbonImmutable;

final readonly class CancelPassAction
{
    public function __construct(
        private ReservationsApi $reservations,
        private MapPassData $mapPassData,
    ) {}

    public function handle(Pass $pass, ?string $reason = null): Pass
    {
        return $pass->getConnection()->transaction(function () use ($pass, $reason): Pass {
            /** @var Pass $locked */
            $locked = OwnerUiScope::apply(Pass::query(), includeGlobal: false)
                ->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status instanceof Cancelled) {
                return $locked;
            }

            $locked->cancelled_at ??= CarbonImmutable::now();
            $locked->status_reason = $reason;
            $locked->status->transitionTo(Cancelled::class);

            $this->reservations->releaseAllocations(
                allocToType: $locked->getMorphClass(),
                allocToId: (string) $locked->getKey(),
            );

            $payload = $this->mapPassData->handle($locked);
            $locked->getConnection()->afterCommit(fn () => event(new PassCancelled($payload)));

            return $locked;
        });
    }
}
