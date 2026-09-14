<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function handle(): void
    {
        OwnerContext::withOwner(
            $this->ownerContext->toOwnerModel(),
            fn (): array => $this->generate(),
        );
    }

    /**
     * @return array{generated: int, skipped: int, failed: int}
     */
    private function generate(): array
    {
        $docService = app(DocService::class);
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->docIds as $docId) {
            $doc = OwnerUiScope::apply(Doc::query(), includeGlobal: false)
                ->whereKey($docId)
                ->first();

            if (! $doc instanceof Doc) {
                $skipped++;

                continue;
            }

            try {
                $docService->generatePdf($doc, save: true);
                $generated++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        Log::info('GenerateDocPdfsJob completed.', [
            'generated' => $generated,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return ['generated' => $generated, 'skipped' => $skipped, 'failed' => $failed];
    }
}
