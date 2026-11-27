<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\EntryPoints\Jobs;

use AIArmada\CashierChip\Actions\SyncCustomersToChip;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContextMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class SyncCustomersToChipJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly ?OwnerJobContext $ownerContext = null,
        public readonly int $chunkSize = 100,
    ) {}

    public function handle(SyncCustomersToChip $action): void
    {
        $owner = OwnerJobContextMapper::resolve($this->ownerContext);

        if ((bool) config('cashier-chip.features.owner.enabled', false) && $owner === null && ! $this->ownerContext?->isExplicitGlobal()) {
            throw new NoCurrentOwnerException('An explicit owner context is required for queued CHIP customer sync.');
        }

        OwnerContext::withOwner(
            $owner,
            fn (): array => $action->handle($this->chunkSize),
        );
    }
}
