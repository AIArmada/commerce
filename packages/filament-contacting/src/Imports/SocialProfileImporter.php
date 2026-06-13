<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Imports;

use AIArmada\Contacting\Models\SocialProfile;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

final class SocialProfileImporter extends Importer
{
    protected static ?string $model = SocialProfile::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('socialable_type')
                ->requiredMapping(),
            ImportColumn::make('socialable_id')
                ->requiredMapping(),
            ImportColumn::make('platform')
                ->requiredMapping(),
            ImportColumn::make('label'),
            ImportColumn::make('handle'),
            ImportColumn::make('url'),
            ImportColumn::make('display_name'),
            ImportColumn::make('is_primary')
                ->castStateUsing(fn (?string $state): bool => $state === 'true' || $state === '1' || $state === 'yes'),
            ImportColumn::make('is_public')
                ->castStateUsing(fn (?string $state): bool => $state !== 'false' && $state !== '0' && $state !== 'no'),
        ];
    }

    public function resolveRecord(): ?SocialProfile
    {
        return new SocialProfile;
    }

    /**
     * @return class-string<SocialProfile>
     */
    public static function getModelLabel(): string
    {
        return SocialProfile::class;
    }

    protected function handleRecordCreation(array $data): SocialProfile
    {
        $record = new SocialProfile;
        $record->fill($data);
        $record->save();

        return $record;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your social profile import has completed and '
            . number_format($import->successful_rows) . ' '
            . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' '
                . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
