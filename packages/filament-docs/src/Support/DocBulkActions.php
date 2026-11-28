<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Support;

use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContextMapper;
use AIArmada\Docs\Contracts\DocumentLifecycle;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\EntryPoints\Jobs\GenerateDocPdfsJob;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
            ? OwnerJobContextMapper::fromModel($owner)
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
        foreach ($records as $record) {
            DocsOwnerScope::assertCanMutateRecord($record, 'Document not found.');
        }

        $result = app(DocumentLifecycle::class)->markSent(array_map('strval', $records->modelKeys()));

        Notification::make()
            ->title($result['marked'] . ' documents marked as sent')
            ->body($result['skipped'] . ' skipped (already sent or ineligible).')
            ->success()
            ->send();

        return $result;
    }
}
