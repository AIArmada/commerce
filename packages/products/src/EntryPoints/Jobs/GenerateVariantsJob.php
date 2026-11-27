<?php

declare(strict_types=1);

namespace AIArmada\Products\EntryPoints\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Products\Actions\GenerateQueuedVariants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class GenerateVariantsJob implements OwnerScopedJob, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    public function __construct(
        public string $productId,
        public ?string $ownerType = null,
        public string | int | null $ownerId = null,
        public bool $ownerIsGlobal = false,
    ) {}

    public function ownerContext(): OwnerJobContext
    {
        return new OwnerJobContext($this->ownerType, $this->ownerId, $this->ownerIsGlobal);
    }

    protected function performJob(): void
    {
        app(GenerateQueuedVariants::class)->execute($this->productId);
    }
}
