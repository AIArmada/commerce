<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\EntryPoints\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\FilamentDocs\Actions\GenerateDocPdfs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class GenerateDocPdfsJob implements OwnerScopedJob, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $docIds
     */
    public function __construct(
        public readonly array $docIds,
        public readonly OwnerJobContext $ownerContext,
    ) {}

    public function ownerContext(): OwnerJobContext
    {
        return $this->ownerContext;
    }

    public function handle(GenerateDocPdfs $generateDocPdfs): void
    {
        $generateDocPdfs->execute($this->docIds, $this->ownerContext);
    }
}
