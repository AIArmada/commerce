<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Actions;

use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContextMapper;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Runtime\DocumentRecords;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class GenerateDocPdfs
{
    public function __construct(private DocumentRecords $docService) {}

    /**
     * @param  array<int, string>  $docIds
     * @return array{generated: int, skipped: int, failed: int}
     */
    public function execute(array $docIds, OwnerJobContext $ownerContext): array
    {
        return OwnerContext::withOwner(
            OwnerJobContextMapper::resolve($ownerContext),
            fn (): array => $this->generate($docIds),
        );
    }

    /**
     * @param  array<int, string>  $docIds
     * @return array{generated: int, skipped: int, failed: int}
     */
    private function generate(array $docIds): array
    {
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($docIds as $docId) {
            $doc = OwnerUiScope::apply(Doc::query(), includeGlobal: false)
                ->whereKey($docId)
                ->first();

            if (! $doc instanceof Doc) {
                $skipped++;

                continue;
            }

            try {
                $this->docService->generatePdf($doc, save: true);
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
