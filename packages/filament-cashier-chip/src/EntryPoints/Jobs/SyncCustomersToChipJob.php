<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\EntryPoints\Jobs;

use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContextMapper;
use AIArmada\FilamentCashierChip\Actions\SyncCustomersToChip;
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
        OwnerContext::withOwner(
            OwnerJobContextMapper::resolve($this->ownerContext),
            fn (): array => $action->handle($this->chunkSize),
        );
    }
}
