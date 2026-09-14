<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Jobs\GenerateDocPdfsJob;
use AIArmada\FilamentDocs\Resources\DocResource\Tables\DocsTable;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DocBulkActions
{
    public const int MAX_PDF_SELECTION = 500;

    public const int PDF_CHUNK_SIZE = 25;

    /**
     * @param  Collection<int|string, Doc>  $records
     */
    public static function dispatchPdfJobs(Collection $records): int
    {
        /** @var array<int, string> $ids */
        $ids = $records->map(static fn (Doc $record): string => (string) $record->getKey())->all();

        if (count($ids) > self::MAX_PDF_SELECTION) {
            Notification::make()
                ->title('Too many documents selected')
                ->body('Select at most ' . self::MAX_PDF_SELECTION . ' documents per bulk PDF run.')
                ->danger()
                ->send();

            return 0;
        }

        $owner = OwnerContext::resolve();
        $context = $owner instanceof Model
            ? OwnerJobContext::fromOwnerModel($owner)
            : OwnerJobContext::explicitGlobal();

        $dispatched = 0;

        foreach (array_chunk($ids, self::PDF_CHUNK_SIZE) as $chunk) {
            GenerateDocPdfsJob::dispatch($chunk, $context);
            $dispatched++;
        }

        Notification::make()
            ->title('PDF generation queued for ' . count($ids) . ' documents')
            ->success()
            ->send();

        return $dispatched;
    }

    /**
     * @param  Collection<int|string, Doc>  $records
     * @return array{marked: int, skipped: int}
     */
    public static function markSelectedAsSent(Collection $records): array
    {
        $result = DB::transaction(function () use ($records): array {
            $marked = 0;
            $skipped = 0;

            foreach ($records as $record) {
                DocsOwnerScope::assertCanMutateRecord($record, 'Document not found.');

                if (! DocsTable::canMarkAsSent($record)) {
                    $skipped++;

                    continue;
                }

                $record->markAsSent();
                $marked++;
            }

            return ['marked' => $marked, 'skipped' => $skipped];
        });

        Notification::make()
            ->title($result['marked'] . ' documents marked as sent')
            ->body($result['skipped'] . ' skipped (already sent or ineligible).')
            ->success()
            ->send();

        return $result;
    }
}
